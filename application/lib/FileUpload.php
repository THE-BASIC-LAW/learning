<?php
namespace app\lib;

use \Exception;
use think\Log;

class FileUpload
{
    const FILE_IMG = 'img';

    public static $img_type = ['image/gif','image/jpeg','image/pjpeg','image/png'];

    public static $file_suffix = [
        'image/gif'   => '.gif',
        'image/png'   => '.png',
        'image/jpeg'  => '.jpg',
        'image/pjpeg' => '.jpg',
    ];

    public static $file_size = 2097152;    //  2M

    public static $root_dir = '';

	public static $host = '';

	public static $files = [];

	public static function img($dir = null, $host = '/'){
		self::set_root_dir($dir);
		self::set_host($host);
        if(count($_FILES)){
            self::check_root_dir();
            foreach($_FILES as $key => $file){
                if(is_array($file['name'])){
                    foreach ($file['name'] as $k => $v){
                        $File['name'] = $file['name'][$k];
                        $File['error'] = $file['error'][$k];
                        $File['type'] = $file['type'][$k];
                        $File['tmp_name'] = $file['tmp_name'][$k];
                        if(!self::check_file($File)) break;
                        $file_save_path = self::file_path()."/".self::file_name($File);
                        self::$files[] = self::save_file($File,$file_save_path);
                    }
                return self::$files;
                }

                if(!self::check_file($file)) break;
                $file_save_path = self::file_path()."/".self::file_name($file);
                self::$files[] = self::save_file($file,$file_save_path);
            }
        }
		return self::$files;
	}

	public static function base64($input, $dir = null, $host = '/'){
        self::set_root_dir($dir);
        self::set_host($host);
        self::check_root_dir();
        if(is_array($input)){
            foreach ($input as $data_url){
                $file_path = self::save_base64($data_url);
                $file_path && self::$files[] = $file_path;
            }
        } else {
            $file_path = self::save_base64($input);
            $file_path && self::$files[] = $file_path;
        }
        return self::$files;
    }

	public static function set_host($host = null){
		if($host){
			self::$host = $host;
		}
	}

	public static function save_file($file, $file_path){
		move_uploaded_file($file['tmp_name'],$file_path);
		if(!file_exists($file_path)){
			throw new Exception("save file failed");
		}
		if(self::$host == '/'){
			return str_replace(self::$root_dir,'',$file_path);
		}else{
			return self::$host.str_replace(self::$root_dir,'',$file_path);
		}
	}

	public static function file_name($file){
		$type = $file['type'];
		return random(4).time().random(4).self::$file_suffix[$type];
	}

	public static function file_path(){
		$path = self::$root_dir;

		if(!is_dir($path)){
			mkdir($path,0777,true);
		}
		if(!is_dir($path)){
			throw new Exception("create File upload File failed");
		}
		return $path;
	}

	public static function set_root_dir($dir = null){
		if($dir){
			self::$root_dir = $dir;
		}
	}

	public static function check_root_dir(){
		if(!self::$root_dir){
			throw new Exception("not set the root dir for upload file");
		}
	}

	protected static function check_file($file){
		if($file['error']) return false;    // file upload wrong
		if(!in_array($file['type'],self::$img_type)) return false;    // file is not picture
		if($file['size'] > self::$file_size) return false;    // file size limited
		return true;
	}

	protected static function save_base64($data_url){
        if (preg_match('/^(data:\s*(image\/\w+);base64,(.*))/', $data_url, $result)){
            $type = $result[2];
            if(!in_array($type, self::$img_type)){
                Log::info('the type is unfitable : ' . $type);
                return false;
            }
            $file_path = random(4).time().random(4).self::$file_suffix[$type];
            if (file_put_contents(self::file_path()."/".$file_path, base64_decode($result[3]))){
                Log::info('file writing succeed : ' . self::file_path()."/".$file_path);
                Log::info('file writing host is : ' . self::$host);
                Log::info('file writing root_dir is : ' . self::$root_dir);
                Log::info('the replace result is : ' . str_replace(self::$root_dir,'',$file_path));
                return self::$host.'/'.$file_path;
            }else{
                Log::info('file writing failed : ' . self::file_path()."/".$file_path);
                return false;
            }
        }
    }
}
// end of file
