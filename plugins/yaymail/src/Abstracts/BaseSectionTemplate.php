<?php
namespace YayMail\Abstracts;

/**
 * BaseSectionTemplate Class
 */
abstract class BaseSectionTemplate {

    protected $icon = '<svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
    viewBox="0 0 24 24" style="enable-background:new 0 0 24 24;" xml:space="preserve">
    <title>Third party elements</title>
    <path d="M18,2h-7.6c-0.1,0-0.1,0-0.2,0.1l0,0c-0.1,0-0.1,0.1-0.2,0.1L4.6,7.4c0,0-0.1,0.1-0.1,0.2l0,0c0,0.1,0,0.1-0.1,0.2l0,0
    c0,0.1,0,0.1,0,0.2v12.5C4.4,21.3,5.1,22,6,22h12c0.9,0,1.6-0.7,1.6-1.6l0,0V3.6C19.6,2.7,18.9,2,18,2L18,2z M9.8,4.5v2.7H7L9.8,4.5
    z M18.1,20.3c0,0.1-0.1,0.2-0.2,0.2c0,0,0,0,0,0H6.1c-0.1,0-0.2-0.1-0.2-0.2c0,0,0,0,0,0V8.7h4.6c0.4,0,0.7-0.3,0.8-0.7V3.5h6.6
    c0.1,0,0.2,0.1,0.2,0.2c0,0,0,0,0,0L18.1,20.3z M7.4,11.6c0-0.4,0.3-0.7,0.7-0.7c0,0,0,0,0,0h4.8c0.4,0,0.8,0.2,0.8,0.7
    c0,0.4-0.2,0.8-0.7,0.8c-0.1,0-0.1,0-0.2,0H8.1C7.7,12.4,7.4,12.1,7.4,11.6C7.4,11.7,7.4,11.7,7.4,11.6z M16.6,14.6
    c0,0.4-0.3,0.7-0.8,0.8H8.1c-0.4,0-0.8-0.3-0.8-0.8s0.3-0.8,0.8-0.8h7.7C16.3,13.9,16.6,14.2,16.6,14.6z M8.6,17.6
    c0,0.4-0.3,0.7-0.7,0.7S7.2,18,7.2,17.6s0.3-0.7,0.7-0.7S8.6,17.2,8.6,17.6z M11,17.6c0,0.4-0.3,0.7-0.7,0.7S9.6,18,9.6,17.6
    c0-0.4,0.3-0.7,0.7-0.7C10.7,16.9,11,17.2,11,17.6L11,17.6z M13.4,17.6c0,0.4-0.2,0.7-0.6,0.8c-0.4,0-0.7-0.2-0.8-0.6
    c0-0.1,0-0.1,0-0.2c0-0.4,0.2-0.7,0.6-0.8c0.4,0,0.7,0.2,0.8,0.6C13.4,17.5,13.4,17.5,13.4,17.6z"/>
    </svg>';

    protected $id     = null;
    public const TYPE = '';
    protected $group  = null;
    protected $name   = null;

    protected $available = true;
    protected $position  = 10;
    protected $patterns  = [];

    public function get_data() {
        return [
            'id'        => $this->id,
            'type'      => static::TYPE,
            'group'     => $this->group,
            'name'      => $this->name,
            'icon'      => $this->icon,
            'available' => $this->available,
            'position'  => $this->position,
            'patterns'  => $this->patterns,
        ];
    }

    public function get_id() {
        return $this->id;
    }

    public function get_type() {
        return static::TYPE;
    }

    public function get_patterns() {
        return $this->patterns;
    }

    public function add_pattern( BasePattern $pattern ) {
        $added_patterns = array_map(
            function( $item ) {
                return $item->get_type();
            },
            $this->patterns
        );
        if ( ! in_array( $pattern->get_type(), $added_patterns, true ) ) {
            $this->patterns[] = $pattern;
        }
    }

    public function get_raw_data() {
        $data             = $this->get_data();
        $data['patterns'] = array_map(
            function( BasePattern $item ) {
                return $item->get_raw_data();
            },
            $data['patterns']
        );
        return $data;
    }

}
