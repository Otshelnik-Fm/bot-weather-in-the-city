<?php

/*

  ╔═╗╔╦╗╔═╗╔╦╗
  ║ ║ ║ ╠╣ ║║║ https://otshelnik-fm.ru
  ╚═╝ ╩ ╚  ╩ ╩

 */


// выведем кнопку
function bwitc_add_button($out, $chat){
    if ( !is_user_logged_in() ) return $out; // гостю это не нужно
    
    if($chat['chat_status'] != 'general') return $out; // в личном чате нам кнопка не нужна

    $arg = [
        'icon'=>'fa-thermometer-half',
        'text'=>'В Саратове',
        'command'=>'weather_in_city',
    ];
    $out .= autobot_add_button($arg); // функция автобота - вернет готовую кнопку с html и атрибутами

    return $out;
}
add_filter('rcl_chat_after_form', 'bwitc_add_button', 19, 2);
// фильтр rcl_chat_before_form - выведет нам кнопу над текстовым полем ввода.
// или rcl_chat_after_form - под текстовом полем


 
// поймаем команду
function bwitc_catch_chat_message($message){
/* $message:
  Array(
    [chat_id] => 145,
    [user_id] => 1,                         // кто писал
    [message_content] => !weather_in_city,
    [message_time] => 2018-03-15 20:42:58,
    [private_key] => 133,                   // кому писал. 0 - если общий чат
    [message_status] => 0,                  // не прочитан
  )*/

    if($message['message_content'] == '!weather_in_city'){          // эту команду мы ждем. Это она!
        $message['message_content'] = 'Занято. Попробуйте позже';   // дефолт. (он нужен если 3-я функция нам false вернет)
        $message['user_id'] = AUTOBOT_ID;                           // константа. Хранит в себе идентификатор автобота.
        //$message['message_time'] = date("Y-m-d H:i:s", current_time('timestamp') - 5); // отнимаем 5 секунд как небольшой фикс при двойном получении в чате

        $resp = bwitc_get_weather(); // запустим запрос к внешнему сайту
        if($resp){                   // успешный ответ. Выводим
            $str = str_replace('<br>', "\n", $resp);
            $message['message_content'] = $str;
        }

    }

    return $message;
}
add_filter('rcl_pre_insert_chat_message', 'bwitc_catch_chat_message');


// удаленный запрос и формирование данных
function bwitc_get_weather(){
    $sity = 11146;              // Саратов region="11146". Смотри в https://pogoda.yandex.ru/static/cities.xml

    $resp = wp_remote_get('https://export.yandex.ru/bar/reginfo.xml?region='.$sity);

    if(wp_remote_retrieve_response_code($resp) != '200') return false; // не 200-й ответ. Или забанили или сервис недоступен

    $body = wp_remote_retrieve_body($resp);
    $xml = new SimpleXMLElement($body);     // все данные у нас в переменной в xml формате. Теперь собрать осталось

    $out = 'Погода: '.$xml->weather->day->title.'<br>';         // город
    $out .= 'Рассвет '.$xml->weather->day->sun_rise.'<br>';
    $out .= 'Закат '.$xml->weather->day->sunset.'<br>';
    $out .= 'Световой день: '.date( "H:i", strtotime($xml->weather->day->sunset) - strtotime($xml->weather->day->sun_rise) ).'<br>';

    foreach($xml->weather->day->day_part as $day){ // утро-день-ночь
        if( isset($day->weather_type) ){
            $out .= 'Сейчас:<br>';
        } else {
            $out .= $day->attributes()->{'type'}.':<br>';
        }
        
        if( isset($day->weather_type) ) $out .= '&nbsp;&nbsp;'.$day->weather_type.'<br>';
        if( isset($day->wind_speed) ) $out .= '&nbsp;&nbsp;Скорость ветра: ' . $day->wind_speed . ' м/с <br>';
        if( isset($day->wind_direction) ) $out .= '&nbsp;&nbsp;Направление: ' . $day->wind_direction . '<br>';
        if( isset($day->dampness) ) $out .= '&nbsp;&nbsp;Влажность: ' . $day->dampness . '%<br>';
        if( isset($day->pressure) ) $out .= '&nbsp;&nbsp;Давление: ' . $day->pressure . ' мм рт. ст.<br>';
        if( isset($day->temperature) ) $out .= '&nbsp;&nbsp;Температура: ' . $day->temperature . '°C<br>';
        if( isset($day->temperature_from) ) $out .= '&nbsp;&nbsp;Температура от: ' . $day->temperature_from . ' до: '. $day->temperature_to . '°C<br>';
    }

    return $out;
}



