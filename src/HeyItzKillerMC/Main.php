<?php

namespace HeyItzKillerMC;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Color;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class Main extends PluginBase implements Listener {

    /** @var Config */
    private $config;

    /** @var array */
    private $staffChatToggled;

    public function onEnable() : void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("config.yml");
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->staffChatToggled = [];
    }

    public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if (isset($this->staffChatToggled[$player->getName()]) || strpos($message, "!") === 0) {
            if (strpos($message, "!") === 0 && !$this->getRank($player)) {
                $message = substr($message, 1);
            } else {
                $event->cancel();
            }

            $rank = $this->getRank($player);

            if (!$rank && strpos($message, "!") === 0) {
                return;
            }

            $format = $this->config->get("staff_chat_format");
            $formattedMessage = TextFormat::colorize(str_replace(["{RANK}", "{PLAYER}", "{MSG}"], [$rank, $player->getName(), ltrim($message, "!")], $format));

            foreach ($this->getServer()->getOnlinePlayers() as $onlinePlayer) {
                if ($this->getRank($onlinePlayer) || $onlinePlayer->getServer()->isOp($onlinePlayer->getName())) {
                    $onlinePlayer->sendMessage($formattedMessage);
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "staffchat" || $command->getName() === "sc") {
            if ($sender instanceof Player) {
                if ($this->getRank($sender)) {
                    $playerName = $sender->getName();
                    if (isset($this->staffChatToggled[$playerName])) {
                        unset($this->staffChatToggled[$playerName]);
                        $sender->sendMessage(TextFormat::RED . "Staff chat toggled off.");
                        $rank = $this->getRank($sender);
                        $rankColor = $rank ? $this->config->getNested("ranks." . $rank . ".color") : "white";
                        $colorCode = $rankColor;
                        $this->getServer()->broadcastMessage(TextFormat::ITALIC . TextFormat::GRAY . "[" . $colorCode . $sender->getName() . TextFormat::GRAY . ": has toggled " . TextFormat::RED . "off " . TextFormat::GRAY . "staff chat]");
                    } else {
                        $this->staffChatToggled[$playerName] = true;
                        $sender->sendMessage(TextFormat::GREEN . "Staff chat toggled on.");
                        $rank = $this->getRank($sender);
                        $rankColor = $rank ? $this->config->getNested("ranks." . $rank . ".color") : "white";
                        $colorCode = $rankColor;
                        $this->getServer()->broadcastMessage(TextFormat::ITALIC . TextFormat::GRAY . "[" . $colorCode . $sender->getName() . TextFormat::GRAY . ": has toggled " . TextFormat::GREEN . "on " . TextFormat::GRAY . "staff chat]");
                    }
                } else {
                    $sender->sendMessage(TextFormat::RED . "You don't have permission to use this command.");
                }
            } else {
                $sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
            }
            return true;
        }
        return false;
    }

    private function getColorValues(string $colorStr): array {
        $color = str_replace(" ", "", strtolower($colorStr));
        $color = preg_replace("/[^a-f0-9]/", "", $color);
        if (strlen($color) === 3) {
            $color = str_repeat(substr($color, 0, 1), 2) . str_repeat(substr($color, 1, 1), 2) . str_repeat(substr($color, 2, 1), 2);
        }
        return [hexdec(substr($color, 0, 2)), hexdec(substr($color, 2, 2)), hexdec(substr($color, 4, 2))];
    }


    private function getRank($player) {
        if ($player->getServer()->isOp($player->getName())) {
            return "OP";
        }

        $ranks = $this->config->getNested("ranks");

        if ($ranks !== null) {
            foreach ($ranks as $rank => $permission) {
                if ($player->hasPermission($permission)) {
                    return $rank;
                }
            }
        }
        return false;
    }
}