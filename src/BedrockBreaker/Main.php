<?php

declare(strict_types=1);

namespace BedrockBreaker;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;

use pocketmine\player\Player;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;

use pocketmine\entity\Entity;
use pocketmine\entity\object\PrimedTNT;

class Main extends PluginBase implements Listener {

    /** @var array<string, int> */
    private array $activeTNT = [];

    /** @var array<string, int> */
    private array $lightCooldown = [];

    /** @var array<int, string> */
    private array $tntOwners = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /* -------------------------
       TNT PLACE LIMIT
    --------------------------*/

    public function onPlace(BlockPlaceEvent $event): void {

        $player = $event->getPlayer();

        if(!$player->hasPermission("bedrockbreaker.use")){
            return;
        }

        foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){

            if($block->getTypeId() === BlockTypeIds::TNT){

                $name = $player->getName();
                $limit = (int)$this->getConfig()->get("tnt-limit");

                $this->activeTNT[$name] = $this->activeTNT[$name] ?? 0;

                if($this->activeTNT[$name] >= $limit){
                    $event->cancel();
                    $player->sendMessage($this->getConfig()->get("messages")["tnt-limit"]);
                    return;
                }
            }
        }
    }

    /* -------------------------
       FLINT & STEEL COOLDOWN + OWNER TRACK
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

            // Increase active TNT count
            $this->activeTNT[$name] = ($this->activeTNT[$name] ?? 0) + 1;
        }
    }

    /* -------------------------
       TRACK TNT ENTITY OWNER
    --------------------------*/

    public function onSpawn(EntitySpawnEvent $event): void {

        $entity = $event->getEntity();

        if(!$entity instanceof PrimedTNT){
            return;
        }

        $nearest = $entity->getWorld()->getNearestEntity(
            $entity->getPosition(),
            5,
            Player::class
        );

        if($nearest instanceof Player){
            $this->tntOwners[$entity->getId()] = $nearest->getName();
        }
    }

    /* -------------------------
       BEDROCK BREAK + COUNT REDUCE
    --------------------------*/

    public function onExplode(EntityExplodeEvent $event): void {

        $entity = $event->getEntity();

        if(!$entity instanceof PrimedTNT){
            return;
        }

        $owner = $this->tntOwners[$entity->getId()] ?? null;

        if($owner !== null){

            $chance = (int)$this->getConfig()->get("bedrock-break-chance");
            $blocks = $event->getBlockList();
            $world = $entity->getWorld();

            foreach($blocks as $block){

                if($block->getTypeId() === BlockTypeIds::BEDROCK){

                    if(mt_rand(1, 100) <= $chance){
                        $world->setBlock($block->getPosition(), VanillaBlocks::AIR());
                    }
                }
            }

            // Reduce only owner's TNT count
            if(isset($this->activeTNT[$owner]) && $this->activeTNT[$owner] > 0){
                $this->activeTNT[$owner]--;
            }

            unset($this->tntOwners[$entity->getId()]);
        }
    }
}
