<?php

namespace Jdenoc\DoxieConsumer\Scanner;

class DoxieScan {

    private string $name;
    private $size;
    private $modified;

    /**
     * @param array|string $doxieItem
     */
    public function __construct($doxieItem) {
        if(is_string($doxieItem)) {
            $doxieItem = json_decode($doxieItem, true);
        }
        $this->name = $doxieItem['name'];
        $this->size = $doxieItem['size'];
        if(empty($doxieItem['modified'])) {
            $this->modified = date('Y-m-d H:i:s');
        } else {
            $this->modified = $doxieItem['modified'];
        }
    }

    public function __get($key) {
        return $this->{$key};
    }

    public function __toString() {
        return json_encode([
            'name' => $this->name,
            'size' => $this->size,
            'modified' => $this->modified,
        ]);
    }

}
