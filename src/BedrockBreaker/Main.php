<?php

declare(strict_types=1);

namespace BedrockBreaker;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityExplodeEvent;

use pocketmine\player\Player;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;

class Main extends PluginBase implements Listener {

    /** @var array<string, int> */
    private array $placedTNT = [];

    /** @var array<string, int> */
    private array $lightCooldown = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* -------------------------
       TNT PLACE LIMIT
    --------------------------*/

    public function onPlace(BlockPlaceEvent $event): void {

        $player = $event->getPlayer();
        $block = $event->getBlock();

        if(!$player->hasPermission("bedrockbreaker.use")){
            return;
        }

        if($block->getTypeId() === BlockTypeIds::TNT){

            $name = $player->getName();
            $limit = (int)$this->getConfig()->get("tnt-limit");

            $this->placedTNT[$name] = $this->placedTNT[$name] ?? 0;

            if($this->placedTNT[$name] >= $limit){
                $event->cancel();
                $player->sendMessage($this->getConfig()->get("messages")["tnt-limit"]);
                return;
            }

            $this->placedTNT[$name]++;
        }
    }

    /* -------------------------
       FLINT & STEEL COOLDOWN (API 5 SAFE)
    --------------------------*/

    public function onInteract(PlayerInteractEvent $event): void {

        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if(!$player->hasPermission("bedrockbreaker.use")){
            return;
        }

        if($block === null){
            return;
        }

        // Check if using Flint & Steel on TNT
        if($item->getTypeId() === VanillaItems::FLINT_AND_STEEL()->getTypeId()
            && $block->getTypeId() === BlockTypeIds::TNT){

            $name = $player->getName();
            $cooldown = (int)$this->getConfig()->get("light-cooldown-seconds");
            $time = time();

            if(isset($this->lightCooldown[$name])){
                if(($time - $this->lightCooldown[$name]) < $cooldown){
                    $event->cancel();
                    $player->sendMessage($this->getConfig()->get("messages")["cooldown"]);
                    return;
                }
            }

            $this->lightCooldown[$name] = $time;
        }
    }

    /* -------------------------
       BEDROCK BREAK LOGIC
    --------------------------*/

    public function onExplode(EntityExplodeEvent $event): void {

        $chance = (int)$this->getConfig()->get("bedrock-break-chance");
        $blocks = $event->getBlockList();
        $world = $event->getEntity()->getWorld();

        foreach($blocks as $block){

            if($block->getTypeId() === BlockTypeIds::BEDROCK){

                if(mt_rand(1, 100) <= $chance){
                    $world->setBlock($block->getPosition(), VanillaBlocks::AIR());
                }
            }
        }

        // Reduce TNT counter globally (simple reset logic)
        foreach($this->placedTNT as $player => $count){
            if($count > 0){
                $this->placedTNT[$player]--;
            }
        }
    }
}
