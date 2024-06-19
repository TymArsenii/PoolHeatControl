<?php

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



$sql="SELECT `dis_temp`, `en_temp` FROM `pool_heating` ORDER BY `id` DESC LIMIT 1";
$res=mysqli_query($db_connect, $sql);//or die(mysql_error());
if(mysqli_num_rows($res)==1)
{
  $settings_arr=mysqli_fetch_assoc($res);
}
else // `id`, `date`, `curr_temp`, `dis_temp`, `en_temp`, `state`
{
  $settings_arr['dis_temp']='n/a';
  $settings_arr['en_temp']='n/a';
}
?>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"//www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="//www.w3.org/1999/xhtml">
<head>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>PoolHeatMonitor</title>

  <script src="https://cdn.plot.ly/plotly-latest.min.js"></script>

  
	<link rel="Website Icon" type="png"
	href="swimming-pool.png">
</head>
<style>
.checkbox_span:hover
{
  color:red;
}

.typical_div
{
  border:2px solid #aaa; 
  padding:10px;
}
</style>

<body>


<div style="display:inline-block;">



<div class="typical_div">
      <label style="display:inline-block; padding-right:15px;">
        <span>Disable Temp</span>
        <input type="number" id="disable_temp" value="<?php echo $settings_arr['dis_temp']; ?>" style="margin-top:5px; width:50px;">
      </label>
      <label style="display:inline-block;">
        <span>Enable Temp&nbsp;</span>
        <input type="number" id="enable_temp" value="<?php echo $settings_arr['en_temp']; ?>" style="margin-top:5px; width:50px;">
      </label>
      <button style="display:block; margin-top:5px; margin-bottom:5px;" id="button_send_temps">Apply</button>
  </div>

  <br>

  <div class="typical_div">
            <label style="display:inline-block;">
              <span>[date]>=</span>
              <input type="date" id="from_date_inp" value="<?php echo date('Y-m-d'); ?>" style="margin-right:5px;">
            </label>
            <label style="display:inline-block;">
              <span>[date]<=</span>
              <input type="date" id="to_date_inp" value="<?php echo date('Y-m-d'); ?>" style="margin-right:5px;">
            </label>
            <br>
            <button style="display:inline-block; margin-top:5px; margin-right:10px;" id="button_send">Apply</button>
            <label style="display:inline-block;">
              <input type="checkbox" id="graph_auto">
              <span class="checkbox_span">auto apply</span>
            </label>
  </div>

  


  <br>
</div>
<br>
<div class="typical_div">
    <p style="margin-top:0; font-size:larger; display:inline-block;" id="curr_temp_disp">Loading...</p>
    <p style="margin-top:0; font-size:larger; display:inline-block; color:red;" id="data_warning">&nbsp;</p>
    <p style="margin-top:0; font-size:larger; display:block;" id="state_data">State: loading...</p>
</div>


<div id="daily_plot" style="width:100%; height:100%;"></div>
<p id="no_data_text" style="display:none;">No Data</p>

<script>
setTimeout(update_curr_temp, 1000);
function update_curr_temp()
{
  setTimeout(update_curr_temp, 1000);
  var url='PoolHeatMonitor.php';

  let send_build;
  send_build='request_temp=last';
  
  fetch(url, 
  {
    method: 'POST',
    headers: 
    {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams(send_build)
  })
  .then(response => response.text())
  .then(text => 
  {
    console.log(text);
    decoded_text_tmp=JSON.parse(text);
    document.getElementById('curr_temp_disp').innerHTML='Current Temperature: '+decoded_text_tmp.curr_temp+'°C &nbsp;  |  &nbsp;';
    document.getElementById('state_data').innerHTML='State: '+(decoded_text_tmp.status ? 'Pumping' : 'Idle');
    if(decoded_text_tmp.state=='old')
    {
      document.getElementById('data_warning').style.color='red';
    } 
    else if(decoded_text_tmp.state=='ok')
    {
      document.getElementById('data_warning').style.color='black';
    } 
    document.getElementById('data_warning').innerHTML='last updated: '+decoded_text_tmp.date;
  })
  .catch(error => 
  {
    //console.error('Error:', error);
  });
} 

let temps_arr=[];
let ids_arr=[];

document.getElementById('button_send_temps').addEventListener('click', function() 
{
  var url='PoolHeatMonitor.php';
  var unixTimestamp=Math.floor(Date.now()/1000);
  var data={date: unixTimestamp};

  let disable_temp_inp_send = document.getElementById('disable_temp').value;
  let enable_temp_inp_send = document.getElementById('enable_temp').value;
  if(disable_temp_inp_send>=enable_temp_inp_send)
  {
    enable_temp_inp_send=parseInt(disable_temp_inp_send)+1;
    document.getElementById('enable_temp').value=enable_temp_inp_send;
  }
  let send_build;
  send_build='disable_temp='+disable_temp_inp_send+'&';
  send_build+='enable_temp='+enable_temp_inp_send;
  
  fetch(url, 
  {
      method: 'POST',
      headers: 
      {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams(send_build)
  })
});

document.getElementById('button_send').addEventListener('click', graph_processing);
setTimeout(auto_graph_check, 3000);
function auto_graph_check()
{
  setTimeout(auto_graph_check, 3000);
  let graph_auto_state=document.getElementById("graph_auto").checked;
  if(graph_auto_state) graph_processing();
} 
function graph_processing()
{

  //document.getElementById('button_send').addEventListener('click', function() 
  //{
    var url='PoolHeatMonitor.php';
    var unixTimestamp=Math.floor(Date.now()/1000);
    var data={date: unixTimestamp};

    const date_from_inp_send = document.getElementById('from_date_inp').value;
    const date_to_inp_send = document.getElementById('to_date_inp').value;
    let send_build;
    send_build='request_date_from='+date_from_inp_send+'&';
    send_build+='request_date_to='+date_to_inp_send;
    
    fetch(url, 
    {
      method: 'POST',
      headers: 
      {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: new URLSearchParams(send_build)
    })
    .then(response => response.text())
    /*
    .then(text => 
    {
      const json_strings = text.split('][');

      json_strings[0] += ']';
      for (let i = 1; i < json_strings.length - 1; i++) {
        json_strings[i] = '[' + json_strings[i] + ']';
      }
      json_strings[json_strings.length - 1] = '[' + json_strings[json_strings.length - 1];

      const data_array = json_strings.map(json_str => JSON.parse(json_str));

      return data_array;
    })
    .then(data_array => 
    {
      console.log(data_array);
      for(let id=0; id<temps_arr.length; id++) temps_arr[id]=null;
      for(let id=0; id<ids_arr.length; id++) ids_arr[id]=null;
      temps_arr.length=0;
      ids_arr.length=0;
      for(let id=0; id<data_array.length; id++)
      {
        //const decode_arr=JSON.parse(data_array[id]);
        //temps_arr[id]=decode_arr.temp;
        if(data_array[id][0].date) {;}

        const date_string=data_array[id][0].date;
        const [date_part, time_part]=date_string.split(' ');
        const [year, month, day]=date_part.split('-').map(Number);
        const [hours, minutes, seconds]=time_part.split(':').map(Number);

        temps_arr[id]=data_array[id][0].temp;
        ids_arr[id]=data_array[id][0].id;

        //console.log(ids_arr[id]);
      }
      let min_val=ids_arr[ids_arr.length-1]-1;
      for(let id=0; id<ids_arr.length; id++)
      {
        ids_arr[id]-=min_val;
      }
      console.log('start');
      console.log(ids_arr);
      console.log(temps_arr);
      console.log('end');

      const plot_data= 
      [{
        x: ids_arr,
        y: temps_arr,
        mode: "lines",
        type: "scatter"
      }];

      const plot_layout= 
      {
        xaxis: {title: "Measurments"},
        yaxis: {title: "Temperature"},
        title: "Graph"
      };

      Plotly.newPlot("daily_plot", plot_data, plot_layout);
    })*/

    .then(text => 
    {
      if(text.length>0)
      {
        temps_arr.length=0;
        ids_arr.length=0;

        let decoded_text_tmp;
        decoded_text_tmp=JSON.parse(text);

        console.log('text_arr='+text+"\r\n");
        
        for(let id=0; id<decoded_text_tmp.length; id++)
        {
          //console.log("decode_text_tmp="+decoded_text_tmp[id].curr_temp);
          ids_arr[id]=decoded_text_tmp[id].id;
          temps_arr[id]=decoded_text_tmp[id].curr_temp;
          //console.log("temps_arr["+id+']='+temps_arr[id]);
        }

        console.log('start');
        console.log(ids_arr);
        console.log(temps_arr);
        console.log('end');
      
        const plot_data= 
        [{
          x: ids_arr,
          y: temps_arr,
          mode: "lines",
          type: "scatter"
        }];

        const plot_layout= 
        {
          xaxis: {title: "Measurments"},
          yaxis: {title: "Temperature"},
          title: "Graph"
        };

        Plotly.newPlot("daily_plot", plot_data, plot_layout);
      }
      else
      {
        console.log('no data');
        document.getElementById('no_data_text').style.display='block';
        document.getElementById('daily_plot').style.display='none';
      }
    })
    .catch(error => 
    {
      //console.error('Error:', error);
    });
  //});
}
</script>
</body>
