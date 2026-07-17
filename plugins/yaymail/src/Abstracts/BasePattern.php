<?php
namespace YayMail\Abstracts;

/**
 * BasePattern Class
 */
abstract class BasePattern {
    protected $id     = null;
    public const TYPE = '';

    protected $section = null;

    protected $available   = true;
    protected $position    = 10;
    protected $name        = '';
    protected $elements    = [];

    public function get_data() {
        return [
            'id'          => $this->id,
            'type'        => static::TYPE,
            'section'     => $this->section,
            'available'   => $this->available,
            'position'    => $this->position,
            'elements'    => $this->elements,
            'name'        => $this->name,
        ];
    }

    public function get_type() {
        return static::TYPE;
    }

    /**
     * @return string
     */
    public function get_section() {
        return $this->section;
    }

    public function get_raw_data() {
        return $this->get_data();
    }

}
