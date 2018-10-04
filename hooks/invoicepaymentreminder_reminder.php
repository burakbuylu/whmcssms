<?php
use WHMCS\Database\Capsule;

$hook = array(
    'hook' => 'InvoicePaymentReminder',
    'function' => 'InvoicePaymentReminder_Reminder',
    'description' => array(
        'turkish' => 'Ödenmemiş fatura için bilgi mesajı gönderir',
        'english' => 'Invoice payment reminder'
    ),
    'type' => 'client',
    'extra' => '',
    'defaultmessage' => 'Sayin {firstname} {lastname}, {duedate} son odeme tarihli bir faturaniz bulunmaktadir. Detayli bilgi icin sitemizi ziyaret edin. www.aktuelhost.com',
    'variables' => '{firstname}, {lastname}, {duedate}'
);

if(!function_exists('InvoicePaymentReminder_Reminder')){
    function InvoicePaymentReminder_Reminder($args){

        if($args['type'] == "reminder"){
            $class = new AktuelSms();
            $template = $class->getTemplateDetails(__FUNCTION__);
            if($template['active'] == 0){
                return null;
            }
            $settings = $class->getSettings();
            if(!$settings['api'] || !$settings['apiparams'] || !$settings['gsmnumberfield'] || !$settings['wantsmsfield']){
                return null;
            }
        }else{
            return false;
        }

        $result = Capsule::select("
        SELECT a.duedate,b.id as userid,b.firstname,b.lastname,`c`.`value` as `gsmnumber` FROM `tblinvoices` as `a`
        JOIN tblclients as b ON b.id = a.userid
        JOIN `tblcustomfieldsvalues` as `c` ON `c`.`relid` = `a`.`userid`
        JOIN `tblcustomfieldsvalues` as `d` ON `d`.`relid` = `a`.`userid`
        WHERE a.id = '".$args['invoiceid']."'
        AND `c`.`fieldid` = '".$settings['gsmnumberfield']."'
        AND `d`.`fieldid` = '".$settings['wantsmsfield']."'
        AND `d`.`value` = 'on'
        LIMIT 1
    ");

        if($result){
            $UserInformation = $result;
            $template['variables'] = str_replace(" ","",$template['variables']);
            $replacefrom = explode(",",$template['variables']);
            $replaceto = array($UserInformation->firstname,$UserInformation->lastname,$class->changeDateFormat($UserInformation->duedate));
            $message = str_replace($replacefrom,$replaceto,$template['template']);

            $class->setGsmnumber($UserInformation->gsmnumber);
            $class->setMessage($message);
            $class->setUserid($UserInformation->userid);
            $class->send();
        }
    }
}

return $hook;