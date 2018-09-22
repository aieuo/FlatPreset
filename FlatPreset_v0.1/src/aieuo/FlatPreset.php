<?php
namespace aieuo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\level\generator\generatorManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\math\Vector3;
use pocketmine\block\Block;

class FlatPreset extends PluginBase implements Listener{

    public function onEnable(){
            $this->getServer()->getPluginManager()->registerEvents($this,$this);
            if(!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder(), 0721, true);
        	$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML);
    }

	public function onCommand(CommandSender $sender, Command $command,string $label, array $args):bool{
		$cmd = $command->getName();
		$name = $sender->getName();
		if($cmd == "preset"){
			if(!isset($args[0]))return false;
			switch ($args[0]) {
				case 'pos1':
					$this->pos1break[$name] = true;
					unset($this->pos2[$name]);
					$sender->sendMessage("ブロックを壊してください");
					return true;
				case 'pos2':
					if(!isset($this->pos1[$name])){
						$sender->sendMessage("まずpos1を設定してください");
						return true;
					}
					$this->pos2break[$name] = true;
					$sender->sendMessage("ブロックを壊してください");
					return true;
				case 'create':
					if(!isset($args[1])){
						$sender->sendMessage("/preset create <name>");
						return true;
					}
					if($this->config->exists($args[1])){
						$sender->sendMessage("その名前は既に作成されています");
						return true;
					}
					$this->config->set($args[1],[]);
					$this->config->save();
					$sender->sendMessage("作成しました\n/preset edit <名前> で編集できます");
					return true;
					break;
				case 'generate':
					if(!isset($args[1])){
						$sender->sendMessage("/preset generate <ワールドの名前> <プリセットの名前>");
						return true;
					}
					if(!isset($args[2])){
						if(!isset($this->pos1[$name]) or !isset($this->pos2[$name])){
							$sender->sendMessage("まずposを設定してください");
							return true;
						}
						if($this->getServer()->isLevelGenerated($args[1])){
							$player->sendMessage("そのワールドは既に存在します");
							return true;
						}
						$bottom = min($this->pos1[$name]["y"],$this->pos2[$name]);
						$top = max($this->pos1[$name]["y"],$this->pos2[$name]);
						$level = $this->getServer()->getLevelByName($this->pos1[$name]["level"]);
						$preset = "2;";
						$count = 1;
						$b = $level->getBlock(new Vector3($this->pos1[$name]["x"],$bottom,$this->pos1[$name]["z"]));
						$last = $b->getId().":".$b->getDamage();
						for($y= $bottom +1; $y <=$top; $y++) {
							$block = $level->getBlock(new Vector3($this->pos1[$name]["x"],$y,$this->pos1[$name]["z"]));
							$id = $block->getId();
							$meta = $block->getDamage();
							if($last !== $id.":".$meta){
								$preset = $preset.$count."x".$last.",";
								$count = 1;
								$last = $id.":".$meta;
							}else{
								$count ++;
							}
							if($y == $top){
								$preset = $preset.$count."x".$last.",";
							}
						}
						$preset = str_replace(",;",";",($preset.";1"));
						$this->getServer()->generateLevel($args[1], null, generatorManager::getGenerator("flat"),["preset"=>$preset]);
						$sender->sendMessage("ワールドを作成しました");
					}else{
						if(!$this->config->exists($args[2])){
							$sender->sendMessage("そんなものはありません");
							return true;
						}
						$datas = $this->config->get($args[2]);
						$count = count($datas);
						$preset = ";1";
						for ($i=1; $i <= $count; $i++) {
							if($i != 1)$preset = ",".$preset;
							$preset = $datas[$i]["height"]."x".$datas[$i]["id"].$preset;
						}
						$preset = "2;".$preset;
						$this->generate($args[1],$preset,$sender);
					}
					return true;
					break;
				default:
					return false;
					break;
			}
		}
	}

	public function onBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$name = $player->getName();
		if(isset($this->pos1break[$name])){
			$event->setCancelled();
			$block = $event->getBlock();
			$this->pos1[$name] = [
				"x" => $block->x,
				"y" => $block->y,
				"z" => $block->z,
				"level" => $block->level->getFolderName()
			];
			unset($this->pos1break[$name]);
			$player->sendMessage("設定しました(".$this->pos1[$name]["x"].",".$this->pos1[$name]["y"].",".$this->pos1[$name]["z"].",".$this->pos1[$name]["level"].")");
		}elseif(isset($this->pos2break[$name])){
			$event->setCancelled();
			$block = $event->getBlock();
			if($block->x !== $this->pos1[$name]["x"] or $block->z !== $this->pos1[$name]["z"]){
				$player->sendMessage("高さ以外は最初に設定したのと同じ場所にしてください\n(".$this->pos1[$name]["x"].", y ,".$this->pos1[$name]["z"].",".$this->pos1[$name]["level"].")");
				return;
			}
			$this->pos2[$name] = $block->y;
			unset($this->pos2break[$name]);
			$player->sendMessage("設定しました");
		}
	}
}
