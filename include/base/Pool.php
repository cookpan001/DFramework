<?php
namespace DF\Base;

class Pool
{
    protected $max = 5;
    protected $config;
    protected $idle = array();
    protected $busy = array();
    
    public function __construct($max = 5)
    {
        $this->max = $max;
    }
}
