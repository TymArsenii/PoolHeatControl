#include <LiquidCrystal_I2C.h>
LiquidCrystal_I2C lcd(0x27, 20, 4);

#include <GyverDS18Array.h>

#define SENSORS_AMOUNT 2
#define MIN_WATER_DELTA 5.0
#define MIN_OUTPUT_TEMP 29.0

uint64_t addr[] = 
{
    0x596ADB5509646128, // OUTPUT sensor
};
GyverDS18Array ds(2, addr, SENSORS_AMOUNT);

#include <GyverTimers.h>

uint32_t request_temp_timer;
uint32_t blink_timer;
float temps_arr[SENSORS_AMOUNT+1]={30.00, 22.00}; // default temps | id1-output water sensor; id2-input water sensor 
bool curr_state=false; // true-on; false-off
bool blink_status;
int time_spent_h, time_spent_m, time_spent_s;

void setup()
{
  pinMode(4, OUTPUT);

  lcd.init();
  lcd.backlight();
  lcd.display();

  ds.requestTemp();
  ds.setResolution(12);
  ds.setParasite(false);

  Timer1.setFrequency(1);
  Timer1.enableISR();
}

void loop()
{
  if(millis()-blink_timer>=1000)
  {
    blink_timer=millis();
    blink_status=!blink_status;
    lcd.setCursor(19, 3);
    lcd.print(blink_status ? "A" : "T");
  }

  if(ds.ready() && millis()-request_temp_timer>=500)
  {
    for(int id=0; id<ds.amount(); id++)
    {
      if(ds.readTemp(id))
      {
        float gotten_temp=ds.getTemp();
        temps_arr[id]=gotten_temp;
      }
    }
    ds.requestTemp();

    // <- sensor processing
    // relay processing ->

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

    // GUI/UI ->

    String temps_str_arr[SENSORS_AMOUNT+1];
    for(int id=0; id<SENSORS_AMOUNT; id++)
    {
      temps_str_arr[id]=temps_arr[id];

      if(temps_str_arr[id].length()>0) 
      {
        temps_str_arr[id].remove(temps_str_arr[id].length()-1);
      }
    }

    String delta_str;
    delta_str=delta;
    if(delta_str.length()>0) 
    {
      delta_str.remove(delta_str.length()-1);
    }

    lcd.setCursor(0, 0);
    lcd.print("                   ");
    lcd.setCursor(0, 0);
    lcd.print(curr_state ? "Pumping " : "Idle ");
    lcd.print(time_spent_h);
    lcd.print(':');
    lcd.print(time_spent_m);
    lcd.print(':');
    lcd.print(time_spent_s);


    lcd.setCursor(0, 2);
    lcd.print("                   ");
    lcd.setCursor(0, 2);
    lcd.print("Out t");
    lcd.write((char)223);
    lcd.print(temps_str_arr[0]);
    
    lcd.setCursor(11, 2);
    lcd.print("In t");
    lcd.write((char)223);
    lcd.print(temps_str_arr[1]);

    lcd.setCursor(0, 3);
    lcd.print("                 ");
    lcd.setCursor(0, 3);
    lcd.print("Delta t");
    lcd.write((char)223);
    lcd.print(delta_str);
  }
}

ISR(TIMER1_A)
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
