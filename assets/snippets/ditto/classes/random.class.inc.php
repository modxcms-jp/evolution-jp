<?php
/*********************************
 * RandomLib Version 1.0
 * Programmed by : Chao Xu(Mgccl)
 * E-mail        : mgcclx@gmail.com
 * Website       : http://www.webdevlogs.com
 * Info          : Please email me if there is any feature you want
 * or there is any bugs. I will fix them as soon as possible.
 *********************************/

class random
{
    public $data = [];

    public function add($string, $weight = 1)
    {
        $this->data[] = ['s' => $string, 'w' => $weight];
    }

    public function select_weighted_unique($amount = 1)
    {
        $count = count($this->data);
        $i = 0;
        $return = [];
        if ($amount >= $count) {
            while ($i < $count) {
                $return[] = $this->data[$i]['s'];
                ++$i;
            }
            return $return;
        }

        $max = -1;
        $weighted = 0;
        while ($i < $count) {
            $max += $this->data[$i]['w'];
            if ($this->data[$i]['w'] > 0) {
                $weighted++;
            }
            ++$i;
        }
        // only documents with a weight can be picked
        $amount = min($amount, $weighted);
        $key = [];

        $i = 0;
        $sub = 0;
        while ($i < $amount) {
            $max -= $sub;
            $w = 0;
            $n = 0;
            $num = mt_rand(0, $max);
            while ($w <= $num) {
                $w += $this->data[$n]['w'];
                ++$n;
            }
            $sub = $this->data[$n - 1]['w'];
            $key[] = $this->data[$n - 1]['s'];

            unset($this->data[$n - 1]);
            $this->data = array_merge($this->data);
            ++$i;
        }
        return $key;
    }
}
