<?php

namespace panix\mod\forsage\models;

use panix\engine\base\Model;
use panix\engine\Html;

class ChangesForm extends Model
{

    public static $category = 'forsage';
    protected $module = 'forsage';

    public $date;
    public $time;

    public function rules()
    {
        return [
            [['date', 'time'], "required"],
            ['date', 'datetime', 'format' => 'php:Y-m-d'],
        ];
    }

    public function getTimeList()
    {
        $step = 2;
        $hour = 3600;
        $range = range(0, 24/2);
        $items=[];
        foreach ($range as $steps) {
            $items[]=date('H', time() + $steps);
        }
        return $this->hoursRange(0,86400,3600*2);
        /*[
            '1' => '00:00 - 02:00',
            '2' => '02:00 - 04:00',
        ];*/
    }

    public function hoursRange($lower = 0, $upper = 86400, $step = 3600, $format = 'H:i') {
        $times = [];
        //$now = new \DateTime('now');
        foreach (range($lower, $upper, $step) as $increment) {
            $test= $increment;
            $increment = gmdate('H:i', $increment);

            list($hour, $minutes) = explode( ':', $increment);

            $date = new \DateTime($hour.':'.$minutes); //, new \DateTimeZone("Europe/Kiev")
            $timestamp = $date->getTimestamp();

            //$increment
            $times[$increment] = 'с <strong>'.$date->format($format).'</strong> по <strong>'.date('H:i',$timestamp + $step).'</strong>';
        }

        return $times;
    }

    public function attributeLabels2()
    {
        return [
            'time'=>'time',
            'date'=>'date'
        ];
    }
}
