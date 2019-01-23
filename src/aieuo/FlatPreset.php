<?php
namespace aieuo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\math\Vector3;
use pocketmine\block\Block;

class FlatPreset extends PluginBase implements Listener{

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
        if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0721, true);
    	$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, [
    		"empty" => "2;;1",
    		"stone" => "2;1x7:0,99x1:0;0"
    	]);
    	$this->config->save();
    }

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args):bool{
		$cmd = $command->getName();
		$name = $sender->getName();
		if($cmd == "preset"){
			if(!isset($args[0]))return false;
			switch ($args[0]) {
				case 'pos1':
					$this->break[$name] = 1;
					$sender->sendMessage("ブロックを壊してください");
					return true;
				case 'pos2':
					if(!isset($this->pos[$name][0])){
						$sender->sendMessage("まずpos1を設定してください");
						return true;
					}
					$this->break[$name] = 2;
					$sender->sendMessage("ブロックを壊してください");
					return true;
				case 'save':
					if(!isset($args[1])){
						$sender->sendMessage("/preset save <name>");
						return true;
					}
					if(!isset($this->pos[$name][0]) or !isset($this->pos[$name][1])){
						$sender->sendMessage("まずposを設定してください");
						return true;
					}
					if($this->config->exists($args[1])){
						$sender->sendMessage("その名前は既に使用されています");
						return true;
					}
					$preset = $this->createPreset(...$this->pos[$name]);
					$this->config->set($args[1],$preset);
					$this->config->save();
					$sender->sendMessage("保存しました");
					return true;
				case 'generate':
					if(!isset($args[1])){
						$sender->sendMessage("/preset generate <ワールドの名前> [<プリセットの名前>]");
						return true;
					}
					if(!isset($args[2])){
						if(!isset($this->pos[$name][0]) or !isset($this->pos[$name][1])){
							$sender->sendMessage("まずposを設定してください");
							return true;
						}
						if($this->getServer()->isLevelGenerated($args[1])){
							$sender->sendMessage("そのワールドは既に存在します");
							return true;
						}
						$preset = $this->createPreset(...$this->pos[$name]);
					} else {
						if(!$this->config->exists($args[2])){
							$sender->sendMessage("その名前の物は登録されていません");
							return true;
						}
						$preset = $this->config->get($args[2]);
					}
					$this->getServer()->generateLevel($args[1], null, generatorManager::getGenerator("flat"), ["preset" => $preset]);
					$sender->sendMessage("ワールド ".$args[1]." を作成しました");
					$this->getServer()->loadLevel($args[1]);
					$sender->sendMessage("ワールド ".$args[1]." を読み込みました");
					return true;
				default:
					return false;
			}
		}
	}

	public function onBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$name = $player->getName();
		if(!isset($this->break[$name])) return;
		$event->setCancelled();
		$block = $event->getBlock();
		switch ($this->break[$name]) {
			case 1:
				$this->pos[$name][0] = $block;
				$player->sendMessage("一つ目の場所を設定しました(".$block->x.",".$block->y.",".$block->z.",".$block->level->getFolderName().")");
				break;
			case 2:
				$pos1 = $this->pos[$name][0];
				if($block->x !== $pos1->x or $block->z !== $pos1->z or $block->level->getFolderName() !== $pos1->level->getFolderName()){
					$player->sendMessage("高さ以外は最初に設定したのと同じ場所にしてください\n(".$pos1->x.",?y,".$pos1->z.",".$pos1->level->getFolderName().")");
					break;
				}
				$this->pos[$name][1] = $block;
				$player->sendMessage("二つ目の場所を設定しました(".$block->x.",".$block->y.",".$block->z.",".$block->level->getFolderName().")");
				break;
		}
		unset($this->break[$name]);
	}

	public function createPreset($pos1, $pos2) {
		$top = max($pos1->y, $pos2->y);
		$bottom = min($pos1->y, $pos2->y);
		$level = $pos1->level;
		$blocks = [];
		$count = 1;
		$block = $level->getBlock(new Vector3($pos1->x, $bottom, $pos1->z));
		$last = $block->getId().":".$block->getDamage();
		for($y = $bottom + 1; $y <= $top; $y ++) {
			$block = $level->getBlock($block->add(0, 1));
			$id = $block->getId().":".$block->getDamage();
			if($last === $id) {
				$count ++;
			} else {
				$blocks[] = $count."x".$last;
				$count = 1;
				$last = $id;
			}
		}
		$blocks[] = $count."x".$last;
		return "2;".implode(",", $blocks).";1";
	}
}
