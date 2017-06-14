<?php

namespace jdenoc\DoxieConsumer;

class DoxieScan {

    private $name;
    private $size;
    private $modified;

    /**
     * @param array|string $doxie_item
     */
    public function __construct($doxie_item){
        if(is_string($doxie_item)){
            $doxie_item = json_decode($doxie_item, true);
        }
        $this->name = $doxie_item['name'];
        $this->size = $doxie_item['size'];
        if(empty($doxie_item['modified'])){
            $this->modified = date('Y-m-d H:i:s');
        } else {
            $this->modified = $doxie_item['modified'];
        }
    }

    public function __get($key){
        return $this->{$key};
    }

    public function __toString(){
        return json_encode(array(
            'name'=>$this->name,
            'size'=>$this->size,
            'modified'=>$this->modified
        ));
    }

}