<?php
/* WHMCS SMS Addon with GNU/GPL Licence
 * AktuelHost - http://www.aktuelhost.com
 *
 * https://github.com/AktuelSistem/WHMCS-SmsModule
 *
 * Developed at Aktuel Sistem ve Bilgi Teknolojileri (www.aktuelsistem.com)
 * Licence: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.txt)
 * */
use WHMCS\Database\Capsule;

class AktuelSms{
    var $sender;

    public $params;
    public $gsmnumber;
    public $message;

    public $userid;
    var $errors = array();
    var $logs = array();

    /**
     * @param mixed $gsmnumber
     */
    public function setGsmnumber($gsmnumber){
        $this->gsmnumber = $this->util_gsmnumber($gsmnumber);
    }

    /**
     * @return mixed
     */
    public function getGsmnumber(){
        return $this->gsmnumber;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message){
        $this->message = $this->util_convert($message);
    }

    /**
     * @return mixed
     */
    public function getMessage(){
        return $this->message;
    }

    /**
     * @param int $userid
     */
    public function setUserid($userid){
        $this->userid = $userid;
    }

    /**
     * @return int
     */
    public function getUserid(){
        return $this->userid;
    }

    /**
     * @return array
     */
    public function getParams(){
        $settings = $this->getSettings();
        $params = json_decode($settings['apiparams']);
        return $params;
    }

    /**
     * @return mixed
     */
    public function getSender(){
        $settings = $this->getSettings();
        if(!$settings['api']){
            $this->addError("Geçerli bir api seçilmedi");
            $this->addLog("Geçerli bir api seçilmedi");
            return false;
        }else{
            return $settings['api'];
        }
    }

    /**
     * @return array
     */
    public function getSettings(){
		$result = Capsule::table('mod_aktuelsms_settings')->get();
		return json_decode(json_encode($result[0]),true);
    }

    function send(){
        $sender_function = strtolower($this->getSender());
        if($sender_function == false){
            return false;
        }else{
            $params = $this->getParams();
            $message = $this->message;
            $message .= " ".$params->signature;

            $this->addLog("Params: ".json_encode($params));
            $this->addLog("To: ".$this->getGsmnumber());
            $this->addLog("Message: ".$message);
            $this->addLog("SenderClass: ".$sender_function);

            include_once("senders/".$sender_function.".php");
            $sender = new $sender_function(trim($message),$this->getGsmnumber());
            $result = $sender->send();

            foreach($result['log'] as $log){
                $this->addLog($log);
            }
            if($result['error']){
                foreach($result['error'] as $error){
                    $this->addError($error);
                }

                $this->saveToDb($result['msgid'],'error',$this->getErrors(),$this->getLogs());
                return false;
            }else{
                $this->saveToDb($result['msgid'],'',null,$this->getLogs());
                return true;
            }
        }
    }

    function getBalance(){
        $sender_function = strtolower($this->getSender());
        if($sender_function == false){
            return false;
        }else{
            include_once("senders/".$sender_function.".php");
            $sender = new $sender_function("","");
            return $sender->balance();
        }
    }

    function getReport($msgid){
        $result = Capsule::table('mod_aktuelsms_messages')->where("msgid",$msgid)->take(1)->get();
        $sender_function = strtolower($result[0]->sender);
        if($sender_function == false){
            return false;
        }else{
            include_once("senders/".$sender_function.".php");
            $sender = new $sender_function("","");
            return $sender->report($msgid);
        }
    }

    function getSenders(){
        if ($handle = opendir(dirname(__FILE__).'/senders')) {
            while (false !== ($entry = readdir($handle))) {
                if(substr($entry,strlen($entry)-4,strlen($entry)) == ".php"){
                    $file[] = require_once('senders/'.$entry);
                }
            }
            closedir($handle);
        }
        return $file;
    }

    function getHooks(){
        if ($handle = opendir(dirname(__FILE__).'/hooks')) {
            while (false !== ($entry = readdir($handle))) {
                if(substr($entry,strlen($entry)-4,strlen($entry)) == ".php"){
                    $file[] = require_once('hooks/'.$entry);
                }
            }
            closedir($handle);
        }
        return $file;
    }

    function saveToDb($msgid,$status,$errors = null,$logs = null){
        $now = date("Y-m-d H:i:s");
        $table = "mod_aktuelsms_messages";
        $values = array(
            "sender" => $this->getSender(),
            "to" => $this->getGsmnumber(),
            "text" => $this->getMessage(),
            "msgid" => $msgid,
            "status" => $status,
            "errors" => $errors,
            "logs" => $logs,
            "user" => $this->getUserid(),
            "datetime" => $now
        );
		Capsule::table($table)->insert($values);
        $this->addLog("Mesaj veritabanına kaydedildi");
    }

    /* Main message convert function. Will be removed next release */
    function util_convert($message){
        $changefrom = array('ı', 'İ', 'ü', 'Ü', 'ö', 'Ö', 'ğ', 'Ğ', 'ç', 'Ç','ş','Ş');
        $changeto = array('i', 'I', 'u', 'U', 'o', 'O', 'g', 'G', 'c', 'C','s','S');
        return str_replace($changefrom, $changeto, $message);
    }

    /* Default number format */
    function util_gsmnumber($number){
        $replacefrom = array('-', '(',')', '.', ',', '+', ' ');
        $number = str_replace($replacefrom, '', $number);

        return $number;
    }

    public function addError($error){
        $this->errors[] = $error;
    }

    public function addLog($log){
        $this->logs[] = $log;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        $res = '<pre><p><ul>';
        foreach($this->errors as $d){
            $res .= "<li>$d</li>";
        }
        $res .= '</ul></p></pre>';
        return $res;
    }

    /**
     * @return array
     */
    public function getLogs()
    {
        $res = '<pre><p><strong>Debug Result</strong><ul>';
        foreach($this->logs as $d){
            $res .= "<li>$d</li>";
        }
        $res .= '</ul></p></pre>';
        return $res;
    }

    /*
     * Runs at addon install/update
     * This function controls that if there is any change at hooks files. Such as new hook, variable changes at hooks.
     */
    function checkHooks($hooks = null){
        if($hooks == null){
            $hooks = $this->getHooks();
        }

        $i=0;
        foreach($hooks as $hook){
            $num_rows = Capsule::table('mod_aktuelsms_templates')->where("name",$hook['function'])->where("type",$hook['type'])->take(1)->count();
            if($num_rows == 0){
                if($hook['type']){
                    $values = array(
                        "name" => $hook['function'],
                        "type" => $hook['type'],
                        "template" => $hook['defaultmessage'],
                        "variables" => $hook['variables'],
                        "extra" => $hook['extra'],
                        "description" => json_encode(@$hook['description']),
                        "active" => 1
                    );
					Capsule::table('mod_aktuelsms_templates')->insert($values);
                    $i++;
                }
            }else{
                $values = array(
                    "variables" => $hook['variables']
                );
				Capsule::table('mod_aktuelsms_templates')
					->where('name', $hook['name'])
					->update($values);
            }
        }
        return $i;
    }

    function getTemplateDetails($template = null){
        $data = Capsule::table('mod_aktuelsms_templates')->where("name",$template)->get();
        $row = json_decode(json_encode($data),true);
		return $row[0];
    }

    function changeDateFormat($date = null){
        $settings = $this->getSettings();
        $dateformat = $settings['dateformat'];
        if(!$dateformat){
            return $date;
        }

        $date = explode("-",$date);
        $year = $date[0];
        $month = $date[1];
        $day = $date[2];

        $dateformat = str_replace(array("%d","%m","%y"),array($day,$month,$year),$dateformat);
        return $dateformat;
    }

}
