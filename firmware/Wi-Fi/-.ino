#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <GSON.h>

#define SSID "---"
#define PASS "---"

//#include <LiquidCrystal_I2C.h>
//LiquidCrystal_I2C lcd(0x27, 20, 4);
 
#include <GyverDS18Array.h>

#define SENSORS_AMOUNT 1
#define MIN_WATER_DELTA 5.0
#define MIN_OUTPUT_TEMP 28.0
// #define en_water_temp 40.0
// #define dis_water_temp 29.0

uint64_t addr[] = 
{
  0xED81C95509646128, // OUTPUT sensor  0x596ADB5509646128 0xED81C95509646128
};
GyverDS18Array ds(12, addr, SENSORS_AMOUNT);

bool test_mode=false; //тестовий режим

uint32_t request_temp_timer;
uint32_t blink_timer;
uint32_t wifi_connect_timer;
uint32_t ui_timer;
uint32_t check_server_timer;
uint32_t wifi_disconnect_timer;
uint32_t wifi_check_timer;

float temps_arr[SENSORS_AMOUNT+1]={30.00, 22.00}; // default temps | id1-output water sensor; id2-input water sensor 
bool curr_state=false; // true-pumping; false-idle
bool blink_status;
float en_water_temp=40.0, dis_water_temp=29.0;

float temp_generator=0.0;
int time_spent_h, time_spent_m, time_spent_s;

uint32_t wifi_connection_timer; //часова мітка старту спроби приєднання до wifi
bool wifi_connected=false; //маркер наявності активного wifi зʼєднання протягом N сек 
bool wifi_connected_imid=false; //маркер наявності активного wifi зʼєднання без урахування часу

void IRAM_ATTR timer1_interrupt()
{
  time_spent_s++;
  if (time_spent_s >= 60)
  {
    time_spent_s = 0;
    time_spent_m++;
    if (time_spent_m >= 60)
    {
      time_spent_m = 0;
      time_spent_h++;
    }
  }
}
void setup()
{
  Serial.begin(115200);
  pinMode(4, OUTPUT);
  digitalWrite(4, 1);

  //Wire.begin(14, 5);

  //lcd.init();
  //lcd.backlight();
  //lcd.display();

  ds.requestTemp();
  ds.setResolution(9);
  ds.setParasite(false);

  timer1_isr_init();
  timer1_attachInterrupt(timer1_interrupt);
  timer1_enable(TIM_DIV16, TIM_EDGE, TIM_LOOP);
  timer1_write(6000000);

  WiFi.mode(WIFI_STA);
  WiFi.begin(SSID, PASS);
  //lcd.setCursor(1, 1);
  //lcd.print("Connecting");
  Serial.print("Connecting");
  wifi_connect_timer=millis();
  bool connection_succesful=true;
  int iters=0;
  while(WiFi.status()!=WL_CONNECTED)
  {
    if(millis()-wifi_connect_timer>15000) 
    {
      connection_succesful=false;
      Serial.println("Couldn't connect :-(");
      break;
    }
    delay(500);
    Serial.print(".");
    //lcd.print(".");
    if(iters>7)
    {
      iters=0;
      //lcd.setCursor(11, 1);
      //lcd.print("         ");
      //lcd.setCursor(11, 1);
    }
    iters++;
  }
  //--

  if(connection_succesful) 
  {
    //lcd.setCursor(1, 1);
    //lcd.print("                   ");
    Serial.print("Connected!");
    //lcd.setCursor(3, 1);
    //lcd.print("Connected!");
    delay(1000);
    //lcd.setCursor(1, 1);
    //lcd.print("                   ");
  }
  Serial.println();
   
  if(connection_succesful)
  {
    Serial.print("Local IP: ");
    Serial.println(WiFi.localIP());
  }
  //lcd.clear();
}

// --------------------------------------------------------------------------
// --------------------------------------------------------------------------
// --------------------------------------------------------------------------

void loop()
{
  // wifi_connected - маркер наявності активного wifi зʼєднання протягом N сек 
  // wifi_connected_imid - маркер наявності активного wifi зʼєднання без урахування часу

  if(WiFi.status()==WL_CONNECTED)
  {
    if (!wifi_connected_imid || !wifi_connected)
    {
      wifi_connected_imid=true;
      if(millis()-wifi_check_timer>=2000)
      {
        wifi_connected=true;
      }
    }
  }
  else
  {
    //між спробами приєднання таймаут 30 сек
    if (millis()-wifi_connection_timer>=30000)
    {
      wifi_connection_timer=millis(); //часова мітка старту спроби приєднання до wifi
      wifi_connected=false;
      wifi_connected_imid=false;
      WiFi.begin(SSID, PASS);
      wifi_check_timer=millis();
    }    
  } 

  /*if(millis()-blink_timer>=1000)
  {
    blink_timer=millis();
    blink_status=!blink_status;
    //lcd.setCursor(19, 3);
    //lcd.print(blink_status ? "A" : "T");
  }*/

  // wifi_connected - маркер наявності активного wifi зʼєднання протягом N сек
  if (wifi_connected)
  {
    if(millis()-check_server_timer>=5000)
    {
      check_server_timer=millis();

      WiFiClient client_get;
      HTTPClient http_get;
      http_get.begin(client_get, "http://exch.com.ua/pool_target_temps.txt");
      int httpCode=http_get.GET();
      
      String payload;
      if(httpCode == HTTP_CODE_OK)
      {
        // If the response was successful, print the response payload
        payload="";
        payload=http_get.getString();
        Serial.println(payload);
        // --
        gson::Parser p(10);
        p.parse(payload);

        dis_water_temp=p["disable_temp"];
        en_water_temp=p["enable_temp"];

        Serial.println("==== READ ====");
        Serial.print("dis_water_temp=");
        Serial.println(dis_water_temp);
        Serial.print("en_water_temp=");
        Serial.println(en_water_temp);                
      }
      else
      {
        Serial.println("Error on HTTP request");
      }
      http_get.end();
    }

    // ----------------------------------------------------------------


    if(millis()-request_temp_timer>=3000)
    {
      WiFiClient client;
      HTTPClient http;
      http.begin(client, "http://exch.com.ua/Pool/PoolHeatMonitor.php");
      http.addHeader("Content-Type", "application/x-www-form-urlencoded");
      //--
      request_temp_timer=millis();
      for(int id=0; id<ds.amount(); id++)
      {
        if(ds.readTemp(id) || test_mode)
        {
          float gotten_temp=ds.getTemp();
          //--
          if (test_mode)
          {
            temp_generator+=0.1;
            if(temp_generator>50) temp_generator=0.0;
            temps_arr[id]=temp_generator;
          }
          //--
          temps_arr[id]=gotten_temp;
          //Serial.println(data_post);
        }
      } //for
      //--
      bool state;
      if(temps_arr[0]>=en_water_temp && !curr_state)
      {
        state=true;
      }
      else if(temps_arr[0]<=dis_water_temp && curr_state)
      {
        state=false;
      }
      String data_post="temp=";
      data_post+=temps_arr[0];
      data_post+="&dis_temp=";
      data_post+=dis_water_temp;
      data_post+="&en_temp=";
      data_post+=en_water_temp;
      data_post+="&state=";
      data_post+=state;
        
      int httpCode = http.POST(data_post);
      http.end();
      //--
      ds.requestTemp();

      // <- sensor processing
      // relay processing ->

      /*
      float delta=temps_arr[0]-temps_arr[1];
      if((delta>=(MIN_WATER_DELTA+0.5) && temps_arr[0]>=MIN_OUTPUT_TEMP) && !curr_state)
      {
        digitalWrite(4, 0); // turn on. 0 is on, it's not a mistake!
        curr_state=1;

        Timer1.restart();
        time_spent_s = 0;
        time_spent_m = 0;
        time_spent_h = 0;
      }
      else if((delta<MIN_WATER_DELTA || temps_arr[0]<(MIN_OUTPUT_TEMP-0.5)) && curr_state)
      {
        digitalWrite(4, 1);
        curr_state=0;

        Timer1.restart();
        time_spent_s = 0;
        time_spent_m = 0;
        time_spent_h = 0;
      }
      */
    }

            
  } //if (wifi_connected) 
  else
  {    
    if(millis()-request_temp_timer>=3000)
    {
      request_temp_timer=millis();
      //--
      for(int id=0; id<ds.amount(); id++)
      {
        if(ds.readTemp(id) || test_mode)
        {
          float gotten_temp=ds.getTemp(); 
        }
      }
      ds.requestTemp();
    }   
  }


  // Керування реле -->>
  if(temps_arr[0]>=en_water_temp && !curr_state)
  {
    digitalWrite(4, 0); // turn on. 0 is on, it's not a mistake!
    curr_state=1;

    time_spent_s = 0;
    time_spent_m = 0;
    time_spent_h = 0;
  }
  else if(temps_arr[0]<=dis_water_temp && curr_state)
  {
    digitalWrite(4, 1);
    curr_state=0;

    time_spent_s = 0;
    time_spent_m = 0;
    time_spent_h = 0;
  }
  // Керування реле --<<


  // GUI/UI ->
  /*
  if(millis()-ui_timer>=1000)
  {
    ui_timer=millis();
    
    //String temps_str_arr[SENSORS_AMOUNT+1];
    //for(int id=0; id<SENSORS_AMOUNT; id++)
    //{
    //  temps_str_arr[id]=temps_arr[id];

    //  if(temps_str_arr[id].length()>0) 
    //  {
    //    temps_str_arr[id].remove(temps_str_arr[id].length()-1);
    //  }
    //}
    //String delta_str;
    //delta_str=delta;
    //if(delta_str.length()>0) 
    //{
    //  delta_str.remove(delta_str.length()-1);
    //}

    lcd.setCursor(0, 0);
    lcd.print("                   ");
    lcd.setCursor(0, 0);
    lcd.print(curr_state ? "Pumping " : "Idle ");
    lcd.print(time_spent_h);
    lcd.print(':');
    lcd.print(time_spent_m);
    lcd.print(':');
    lcd.print(time_spent_s);

    lcd.setCursor(0, 1);
    lcd.print("                   ");
    lcd.setCursor(0, 1);
    lcd.print(wifi_connected ? "Wi-Fi" : "Error");

    lcd.setCursor(0, 2);
    lcd.print("                   ");
    lcd.setCursor(0, 2);
    lcd.print("t");
    lcd.write((char)223);
    lcd.print("=");
    lcd.print(temps_arr[0]);

    lcd.setCursor(0, 3);
    lcd.print("                   ");
    lcd.setCursor(0, 3);
    lcd.print("dis=");
    lcd.print(dis_water_temp);
    lcd.print(" en=");
    lcd.print(en_water_temp);
    

    
    //lcd.setCursor(0, 3);
    //lcd.print("                 ");
    //lcd.setCursor(0, 3);
    //lcd.print("Delta t");
    //lcd.write((char)223);
    //lcd.print(delta_str);
    
  }
  */
}
