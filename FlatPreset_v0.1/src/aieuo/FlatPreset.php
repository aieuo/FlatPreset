<?php
namespace aieuo;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\item\Item;


use pocketmine\level\format\io\LevelProvider;
use pocketmine\level\format\io\LevelProviderManager;
use pocketmine\level\generator\Generator;
use pocketmine\level\Level;
use pocketmine\level\LevelException;
use pocketmine\event\level\LevelInitEvent;
use pocketmine\event\level\LevelLoadEvent;

use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

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
				case 'edit':
					//if($sender->getName() == "CONSOLE"){
						if(!isset($args[1])){
							$sender->sendMessage("/preset edit add <名前> <ブロックId> <高さ>\n/preset edit del <名前> <登録したときに出るid>\n/preset edit <up | down> <名前> <登録したときに出るid>\n/preset list <名前>  今の状態、idの確認");
							return true;
						}
						switch ($args[1]) {
							case 'add':
								if(!isset($args[2]) or !isset($args[3]) or !isset($args[4])){
									$sender->sendMessage("/preset edit add <名前> <ブロックId> <高さ>");
									return true;
								}
								if(!$this->config->exists($args[2])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								if(!is_numeric($args[3]) or !is_numeric($args[4])){
									$sender->sendMessage("数字にしてください");
									return true;
								}
								$ids = explode(":",$id);
								if(!isset($ids[1]))$ids[1] = 0;
								$item = Item::get((int)$ids[0],(int)$ids[1],1);
								if(!$item->canBePlaced()){
									$sender->sendMessage("ブロックにしてください");
									return true;
								}
								$datas = $this->config->get($args[2]);
								$count = count($datas) +1;
								$blocks = [
									"id" => $args[3],
									"height" => $args[4]
								];
								$datas[$count] = $blocks;
								$this->config->set($args[2],$datas);
								$this->config->save();
								$sender->sendMessage("追加しました idは".$count."です");
								return true;
								break;
							case 'del':
								if(!isset($args[2]) or !isset($args[3])){
									$sender->sendMessage("/preset edit del <名前> <登録したときに出るid>");
									return true;
								}
								if(!$this->config->exists($args[2])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								$datas = $this->config->get($args[2]);
								$id = (int)$args[3];
								if(!isset($datas[$id])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								unset($datas[$id]);
								foreach ($datas as $key => $value){
									if($key > $id){
										$datas[$key -1] = $datas[$key];
										unset($datas[$key]);
									}
								}
								$this->config->set($args[2],$datas);
								$this->config->save();
								$sender->sendMessage("削除しました\nidが変更されました、/preset list <name>　で確認してください");
								break;
							case 'list':
								if(!isset($args[2])){
									$sender->sendMessage("/preset edit list <名前>");
									return true;
								}
								if(!$this->config->exists($args[2])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								$datas = $this->config->get($args[2]);
								$count = count($datas);
								$sender->sendMessage("下から");
								for ($i=1; $i <= $count; $i++) {
									$sender->sendMessage("id= ".$i."  Blockid: ".$datas[$i]["id"]." x ".$datas[$i]["height"]."ブロック");
								}
								break;
							case 'up':
								if(!isset($args[2]) or !isset($args[3])){
									$sender->sendMessage("/preset edit up <名前> <登録したときに出るid>");
									return true;
								}
								if(!$this->config->exists($args[2])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								$datas = $this->config->get($args[2]);
								$id = (int)$args[3];
								if(!isset($datas[$id])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								if($id == 1){
									$sender->sendMessage("一番上です");
									return true;
								}
								foreach ($datas as $key => $value){
									if($key < $id -1){
										$ndatas[$key] = $datas[$key];
									}elseif($key == $id -1){
										$ndatas[$key +1] = $datas[$key];
									}elseif($key == $id){
										$ndatas[$key -1] = $datas[$key];
									}else{
										$ndatas[$key] = $datas[$key];
									}
								}
								$this->config->set($args[2],$ndatas);
								$this->config->save();
								$sender->sendMessage("変更しました\nidが変更されました、/preset list <name>　で確認してください");
								break;
							case 'down':
								if(!isset($args[2]) or !isset($args[3])){
									$sender->sendMessage("/preset edit up <名前> <登録したときに出るid>");
									return true;
								}
								if(!$this->config->exists($args[2])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								$datas = $this->config->get($args[2]);
								$id = (int)$args[3];
								if(!isset($datas[$id])){
									$sender->sendMessage("そんなものはありません");
									return true;
								}
								if($id == count($datas)){
									$sender->sendMessage("一番下です");
									return true;
								}
								foreach ($datas as $key => $value){
									if($key < $id){
										$ndatas[$key] = $datas[$key];
									}elseif($key == $id +1){
										$ndatas[$key -1] = $datas[$key];
									}elseif($key == $id){
										$ndatas[$key +1] = $datas[$key];
									}else{
										$ndatas[$key] = $datas[$key];
									}
								}
								$this->config->set($args[2],$ndatas);
								$this->config->save();
								$sender->sendMessage("変更しました\nidが変更されました、/preset list <name>　で確認してください");
								break;
								break;
							default:
								$sender->sendMessage("/preset edit add <名前> <ブロックId> <高さ>\n/preset edit del <名前> <登録したときに出るid>\n/preset edit <up | down> <名前> <登録したときに出るid>\n/preset list <名前>  今の状態、idの確認");
								return true;
							 	break;
						}
					/*}
					if(!isset($args[1])){
						$sender->sendMessage("/preset edit <name>");
						return true;
					}*/
					return true;
					break;
				case 'generate':
					if(!isset($args[1]) or !isset($args[2])){
						$sender->sendMessage("/preset generate <ワールドの名前> <プリセットの名前>");
						return true;
					}
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
					echo $preset;
					$this->generate($args[1],$preset,$sender);
					return true;
					break;
				default:
					return false;
					break;
			}
		}
	}


	public function generate($name,$preset,$player){
		if($this->getServer()->isLevelGenerated($name)){
			$player->sendMessage("そのワールドは既に存在します");
			return false;
		}

		$seed = $seed ?? (unpack("N", random_bytes(4))[1] << 32 >> 32);
		$generator = Generator::getGenerator("FLAT");
		$provider = LevelProviderManager::getProviderByName($providerName = "pmanvil");
		$options["preset"] = $preset;
		try{
			$path = $this->getServer()->getDataPath() . "worlds/" . $name . "/";
			$provider::generate($path, $name, $seed, $generator, $options);
			$level = new Level($this->getServer(), $name, $path, (string) $provider);
			//$this->getServer()->levels[$level->getId()] = $level;
			$level->initLevel();
			$level->setTickRate(1);
		}catch(Throwable $e){
			$player->sendMessage($this->getLanguage()->translateString("pocketmine.level.generationError", [$name, $e->getMessage()]));
			$this->logger->error($this->getLanguage()->translateString("pocketmine.level.generationError", [$name, $e->getMessage()]));
			$this->logger->logException($e);
			return false;
		}
		$this->getServer()->getPluginManager()->callEvent(new LevelInitEvent($level));
		$this->getServer()->getPluginManager()->callEvent(new LevelLoadEvent($level));
		$player->sendMessage("ワールドを作成しました\n".$this->getServer()->getLanguage()->translateString("pocketmine.level.backgroundGeneration", [$name]));
		$this->getServer()->getLogger()->notice($this->getServer()->getLanguage()->translateString("pocketmine.level.backgroundGeneration", [$name]));
		$spawnLocation = $level->getSpawnLocation();
		$centerX = $spawnLocation->getFloorX() >> 4;
		$centerZ = $spawnLocation->getFloorZ() >> 4;
		$order = [];
		for($X = -3; $X <= 3; ++$X){
			for($Z = -3; $Z <= 3; ++$Z){
				$distance = $X ** 2 + $Z ** 2;
				$chunkX = $X + $centerX;
				$chunkZ = $Z + $centerZ;
				$index = ((($chunkX) & 0xFFFFFFFF) << 32) | (( $chunkZ) & 0xFFFFFFFF);
				$order[$index] = $distance;
			}
		}
		asort($order);
		foreach($order as $index => $distance){
			$chunkX = ($index >> 32);  $chunkZ = ($index & 0xFFFFFFFF) << 32 >> 32;
			$level->populateChunk($chunkX, $chunkZ, \true);
		}
		return true;
	}
}