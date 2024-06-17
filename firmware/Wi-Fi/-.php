<?php
ini_set('error_reporting', E_ALL & ~E_WARNING);
ini_set('display_errors', 'Off');
/*
$post_data=print_r($_POST, true);

//V1
$log_file=$_SERVER['DOCUMENT_ROOT'].'/pool/post_pool_temp_data.txt';
//echo '<div>$log_file='.$log_file.'</div>';
//Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
if (1==2) {$f=@fopen($log_file,"a+");}
else {$f=@fopen($log_file,"a+");} //Одразу очистити файл w
//--
if (!$f) {;} // Error
else
{
  if (flock($f,6))
  {
    @fwrite($f, $post_data);
    @fwrite($f, "\r\n".'$_POST[temp]='.(isset($_POST['temp'])?$_POST['temp']:'n/a'));
    @fclose($f);
  }
}
*/
//Підключення до БД -->>
define('DB_HOST', 'db23.freehost.com.ua');
define('DB_USER', 'lifelinem_exch');
define('DB_PASSWORD', 'dGjqFxa8p');
define('DB_DB', 'lifelinem_exch');
//--
$db_connect=@mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
if (!$db_connect) {echo 'DB connect ERROR'; exit;}
else
{
   //echo 'DB OK; ';
   //все ОК, підключились
   mysqli_select_db($db_connect, DB_DB) ;//or die(mysql_error());
   //--
   mysqli_query($db_connect, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'"); // ;//or die(mysql_error());
   mysqli_query($db_connect, "SET character_set_results='utf8', character_set_client='utf8', character_set_connection='utf8', character_set_database='utf8', 	character_set_server='utf8'"); // ;//or die(mysql_error());
   mysqli_query($db_connect, "SET names utf8"); // ;//or die(mysql_error());
   //mysql_set_charset('utf8');
   mb_internal_encoding('UTF-8');
   // PHP International Language and Locale codes demonstration
   setlocale(LC_ALL,'ru_RU.utf8'); //print_r(setlocale(LC_ALL, NULL)); //вивести інфу про локаль
}
//Підключення до БД --<<

if(isset($_POST['request_temp']))
{
  if($_POST['request_temp']=='last')
  {
    $sql="SELECT `curr_temp`
    FROM `pool_heating` 
    ORDER BY id DESC LIMIT 1";
    $res=mysqli_query($db_connect, $sql);
    $rows_ms=mysqli_fetch_assoc($res);

    $data_final=
    [
      ['curr_temp' => $rows_ms['curr_temp']]
    ];

    header('Content-Type: application/json');
    echo json_encode($data_final[0]);
    exit;
  }
}

if(isset($_POST['disable_temp']))
{
  if($_POST['disable_temp']!=null)
  {
    $gotten_disable_temp=floatval($_POST['disable_temp']);
  }
}
if(isset($_POST['enable_temp']))
{
  if($_POST['disable_temp']!=null)
  {
    $gotten_enable_temp=floatval($_POST['enable_temp']);
  }
}
else
{ 
  /*
  $file_data=file_get_contents('post_pool_temp_data.txt');
  if($file_data!==false)
  {
    $file_decoded_data=json_decode($file_data, true);
    $gotten_enable_temp=intval($file_decoded_data['enable_temp']);
  }
  */
}

if($gotten_disable_temp>=$gotten_enable_temp)
{
  $gotten_enable_temp=$gotten_disable_temp+1;
}

if(isset($gotten_disable_temp) && isset($gotten_enable_temp))
{
  $target_temps_arr=
  [
    ["disable_temp"=>$gotten_disable_temp, "enable_temp"=>$gotten_enable_temp],
  ];

  //V1
  $log_file=$_SERVER['DOCUMENT_ROOT'].'/pool_target_temps.txt';
  //echo '<div>$log_file='.$log_file.'</div>';
  //Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
  if(1==2) {$f=@fopen($log_file,"a+");}
  else {$f=@fopen($log_file,"w");} //Одразу очистити файл w
  //--
  if (!$f) {;} // Error
  else
  {
    if(flock($f,6))
    {
      @fwrite($f, json_encode($target_temps_arr[0])); 
      @fclose($f);
    }
  }
}
//--

$inp_date_from='';
if(1==1 || $_SERVER['REQUEST_METHOD']=='POST') 
{

  function validate_date($date)
  {
    $pattern = '/^\d{4}-\d{2}-\d{2}$/';
    return preg_match($pattern, $date);
  }
  if(isset($_POST['request_date_from']))
  {
    $inp_date_from=$_POST['request_date_from'];
  }
  else
  {
    $inp_date_from=date('Y-m-d'); // H:i:s
  }

  if(isset($_POST['request_date_to']))
  {
    $inp_date_to=$_POST['request_date_to'];
  }
  else
  {
    $inp_date_to=date('Y-m-d'); // H:i:s
  }
  if(!validate_date($inp_date_from)) $inp_date_from=date('Y-m-d');
  if(!validate_date($inp_date_to)) $inp_date_to=date('Y-m-d');

  /*
  $file_data_build=$inp_date_from.'   +   '.$inp_date_to;

  //V1
  $log_file=$_SERVER['DOCUMENT_ROOT'].'/post_pool_test_data.txt';
  //echo '<div>$log_file='.$log_file.'</div>';
  //Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
  if (1==2) {$f=@fopen($log_file,"a+");}
  else {$f=@fopen($log_file,"w");} //Одразу очистити файл w
  //--
  if (!$f) {;} // Error
  else
  {
    if (flock($f,6))
    {
      @fwrite($f, $file_data_build);
      @fclose($f);
    }
  }

  */
  //-- 

  $date=new DateTime($inp_date_to);
  $date->modify('+1 day');
  $new_date=$date->format('Y-m-d');

  /*
  $file_post='$inp_date_from= :'.$inp_date_from.':'. "\r\n";
  $file_post.='$new_date= :'.$new_date.':';
  */
}

if(isset($_POST['temp']))
{
  $sql="INSERT IGNORE INTO `pool_heating` SET
    `date`='".date('Y-m-d H:i:s')."',
    `curr_temp`='".$_POST['temp']."',
    `dis_temp`='".$_POST['dis_temp']."',
    `en_temp`='".$_POST['en_temp']."'";

  mysqli_query($db_connect, $sql);
}

$sql="SELECT `id`, `curr_temp`, `date` 
      FROM `pool_heating` 
      WHERE `date`>='".$inp_date_from."' && `date`<='".$new_date."' 
      ORDER BY id DESC";
//echo '<pre>'.$sql.'</pre>'; 
//echo $inp_date_from.'   :   '.$inp_date_to;
//exit;
$res=mysqli_query($db_connect, $sql);//or die(mysql_error());
 
$data_tmp='';
$data_final='';
//--
$json_mode=2;
if (isset($pool_heating__ms)) {unset($pool_heating__ms);} 
//--
while($rows_ms=mysqli_fetch_assoc($res))
{
  if ($json_mode==1)
  {  
    $data_tmp=
    [
      ["id"=>$rows_ms['id'], "temp"=>$rows_ms['curr_temp'], "date"=>$rows_ms['date']],
    ];
    $data_final.=json_encode($data_tmp);
  }
  else if ($json_mode==2)
  {
    $pool_heating__ms[]=$rows_ms;
  }
  /*
  //V1
  $log_file=$_SERVER['DOCUMENT_ROOT'].'/post_pool_temp_data.txt';
  //echo '<div>$log_file='.$log_file.'</div>';
  //Є різні варіанти відкривання файлів: https://www.php.net/manual/en/function.fopen.php
  if (1==2) {$f=@fopen($log_file,"a+");}
  else {$f=@fopen($log_file,"a+");} //Одразу очистити файл w
  //--
  if (!$f) {;} // Error
  else
  {
    if (flock($f,6))
    {
      @fwrite($f, $data_final);
      @fclose($f);
    }
  }
  */
}

//--

if ($json_mode==1)
{  
  ;
}
elseif ($json_mode==2)
{
  $data_final=json_encode($pool_heating__ms);
}

header('Content-Type: application/json');
echo $data_final;
?>
