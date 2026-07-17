<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Single Banner Elements
 */
class SingleBanner extends BaseElement {

    use SingletonTrait;

    protected static $type = 'single_banner';

    public $available_email_ids = [];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M12.51,17.63H2.75c-.91,0-1.66-.79-1.66-1.75V3.1c0-.96.74-1.75,1.66-1.75h14.51c.91,0,1.66.79,1.66,1.75v11.72h-1.5V3.1c0-.15-.09-.25-.16-.25H2.75c-.06,0-.16.1-.16.25v12.79c0,.15.09.25.16.25h9.76v1.5Z"/>
  <path d="M12.1,13.59c-.65,0-1.29-.28-1.73-.82l-2.53-3.06c-.13-.16-.31-.25-.51-.27-.19-.02-.39.04-.54.17l-4.26,3.6c-.31.27-.79.23-1.06-.09-.27-.32-.23-.79.09-1.06l4.26-3.61c.46-.39,1.06-.58,1.64-.52.6.05,1.15.34,1.53.81l2.53,3.06c.26.31.69.37,1.02.13l5-3.73c.33-.25.8-.18,1.05.15.25.33.18.8-.15,1.05l-5,3.73c-.4.3-.87.44-1.33.44Z"/>
  <path d="M13.26,8.57c-1.09,0-1.98-.89-1.98-1.98s.89-1.98,1.98-1.98,1.98.89,1.98,1.98-.89,1.98-1.98,1.98ZM13.26,6.12c-.26,0-.48.21-.48.48s.21.48.48.48.48-.21.48-.48-.21-.48-.48-.48Z"/>
  <g>
    <rect x="14.79" y="14.95" width="1.5" height="3.56" transform="translate(-5.02 6.79) rotate(-21.32)"/>
    <path d="M13.51,16.55l.64-3.37,1.52,1.13s.02.01.03.02l1.21.9-1.97.77s-.01,0-.02,0l-1.41.55Z"/>
  </g>
</svg>';

        $background_conditions = [
            [
                'comparison' => 'contain',
                'value'      => [ 'background_image' ],
                'attribute'  => 'showing_items',
            ],
        ];

        $button_conditions = [
            [
                'comparison' => 'contain',
                'value'      => [ 'button' ],
                'attribute'  => 'showing_items',
            ],
        ];

        return [
            'id'              => uniqid(),
            'type'            => self::$type,
            'name'            => __( 'Single Banner', 'yaymail' ),
            'icon'            => self::$icon,
            'group'           => 'block',
            'available'       => false,
            'disabled_reason' =>
            [
                'html' => '<svg id="yaymail-single_banner" class="yaymail-element-disabled-icon" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1000 300"><defs><style> #yaymail-single_banner .st0 { fill: #fff; } #yaymail-single_banner .st1 { fill: #edeff3; } #yaymail-single_banner .st2 { fill: #1f2937; } #yaymail-single_banner .st3 { fill: #dee2e9; } #yaymail-single_banner .st4 { fill: #6b7280; } #yaymail-single_banner .st5 { fill: #c9ced6; } </style></defs><g id="Layer_1-2"><g><rect class="st0" width="1000" height="300"/><g><g><g><path class="st2" d="M413.6,64.7v-11.1c0-.3,0-.7-.2-.9l-10.8-19h6.6c2.5,4.8,6.1,11.7,7.7,14.8,1.4-3.1,5.2-10.2,7.7-14.8h6.1l-11,19c-.1.2-.2.4-.2.9v11.2h-5.9Z"/><path class="st2" d="M450.2,53c0,6.9-4.1,12.2-11.3,12.2s-11.1-5.1-11.1-12,4.2-12.2,11.4-12.2,11,4.8,11,12.1h0ZM433.5,53.1c0,4.7,2.1,7.7,5.6,7.7s5.5-2.9,5.5-7.6-2-7.7-5.5-7.7-5.5,2.6-5.5,7.7h0Z"/><path class="st2" d="M474.7,57.9c0,2.2,0,4.9,0,6.7h-5.5c0-.6-.1-1.9-.2-2.9-1.3,2.4-3.4,3.5-6.6,3.5s-7.7-2.5-7.7-8.5v-15.1h5.6v14c0,2.7.9,5.1,4,5.1s4.7-1.8,4.7-6.8v-12.2h5.6v16.4h0Z"/><path class="st2" d="M480.8,47.8c0-2.6,0-4.6,0-6.3h5.5c0,.6.1,2.7.1,4.3,1.1-2.8,3.6-4.8,7.5-4.8v5.4c-4.7,0-7.5,1.6-7.5,7.8v10.5h-5.6v-16.8h0Z"/><path class="st2" d="M530.5,50.8h-15.1v9h16.6l-.7,4.8h-21.6v-31.1h21.4v4.8h-15.8v7.6h15.1v4.8h0Z"/><path class="st2" d="M536.1,64.7V31.7h5.6v32.9h-5.6Z"/><path class="st2" d="M552,54.2c0,3.5,1.8,6.5,5.2,6.5s3.9-1.3,4.6-2.8h5.6c-.8,2.9-3.4,7.3-10.3,7.3s-10.7-5.8-10.7-11.8,3.6-12.4,10.9-12.4,10.4,5.8,10.4,11.3,0,1.3,0,1.9h-15.6ZM562.1,50.7c0-3.1-1.4-5.6-4.8-5.6s-4.8,2.4-5.1,5.6h9.9Z"/><path class="st2" d="M593.1,62.1c0,7.4-2.8,12-11.2,12s-9.8-4.4-10.1-7.2h5.7c.5,1.9,2,3.1,4.6,3.1,4.5,0,5.5-2.8,5.5-7.6v-1.4c-1.2,2.2-3.2,3.5-6.8,3.5-6,0-9.6-4.9-9.6-11.3s4.3-12.2,10.1-12.2,5.8,1.9,6.2,3.2c0-.8.2-2.2.2-2.6h5.3c0,1.8,0,4.4,0,6.7v13.9h0ZM577.1,52.9c0,4.3,2.2,7,5.3,7s5.4-2.9,5.4-7.2-.9-7.1-5.3-7.1-5.5,2.6-5.5,7.4h0Z"/><path class="st2" d="M617.3,59c0,2.2.2,5.1.3,5.7h-5.3c-.2-.5-.3-1.7-.3-2.5-.8,1.4-2.5,3.1-6.7,3.1s-7.9-3.6-7.9-7.2c0-5.2,4.2-7.6,11-7.6h3.5v-1.6c0-1.8-.6-3.7-4-3.7s-3.7,1.4-4,3.1h-5.3c.3-3.8,2.7-7.2,9.6-7.2,6,0,9.3,2.4,9.3,7.8v10.2h0ZM611.8,53.9h-3c-4.1,0-5.9,1.2-5.9,3.8s1.2,3.5,3.7,3.5c4.6,0,5.2-3.1,5.2-6.6v-.7h0Z"/><path class="st2" d="M623.5,47.3c0-2,0-4.1,0-5.8h5.4c.1.6.2,2.5.2,3.2.8-1.6,2.7-3.8,6.9-3.8s7.7,3,7.7,8.9v14.8h-5.6v-14.1c0-2.9-1-4.9-4.1-4.9s-4.9,2.1-4.9,6.4v12.7h-5.6v-17.3h0Z"/><path class="st2" d="M669.2,57.3c-.9,3.9-3.6,7.9-10.1,7.9s-10.7-4.8-10.7-12,4-12.3,11-12.3,9.6,5.3,9.8,8h-5.5c-.5-2-1.7-3.6-4.4-3.6s-5.2,2.9-5.2,7.6,1.9,7.7,5.1,7.7,3.8-1.4,4.5-3.5c0,0,5.5,0,5.5,0Z"/><path class="st2" d="M677.9,54.2c0,3.5,1.8,6.5,5.2,6.5s3.9-1.3,4.6-2.8h5.6c-.8,2.9-3.4,7.3-10.3,7.3s-10.7-5.8-10.7-11.8,3.6-12.4,10.9-12.4,10.4,5.8,10.4,11.3,0,1.3,0,1.9h-15.6ZM688,50.7c0-3.1-1.4-5.6-4.8-5.6s-4.8,2.4-5.1,5.6h9.9Z"/><path class="st2" d="M737.8,49c0,8.6-5.1,16.1-15.1,16.1s-14.7-7.1-14.7-16,5.5-16,15.1-16,14.7,6.5,14.7,15.9ZM714.2,49c0,6.3,2.9,11.3,8.8,11.3s8.8-5.3,8.8-11.3-2.7-11.2-8.9-11.2-8.8,4.8-8.8,11.1h0Z"/><path class="st2" d="M762.6,57.9c0,2.2,0,4.9,0,6.7h-5.5c0-.6-.1-1.9-.2-2.9-1.3,2.4-3.4,3.5-6.6,3.5s-7.7-2.5-7.7-8.5v-15.1h5.6v14c0,2.7.8,5.1,4,5.1s4.7-1.8,4.7-6.8v-12.2h5.6v16.4h0Z"/><path class="st2" d="M768.7,47.8c0-2.6,0-4.6,0-6.3h5.5c0,.6.2,2.7.2,4.3,1.1-2.8,3.7-4.8,7.5-4.8v5.4c-4.7,0-7.5,1.6-7.5,7.8v10.5h-5.6v-16.8h0Z"/><path class="st2" d="M823.5,55.3c-1.3,5.6-5.5,9.8-13.1,9.8s-14.3-7-14.3-15.8,4.8-16.1,14.6-16.1,12,4.6,12.8,9.8h-5.8c-.8-2.7-2.7-5.2-7.1-5.2s-8.4,5.4-8.4,11.3,2.1,11.4,8.5,11.4,6.2-2.9,7-5.1h5.9,0Z"/><path class="st2" d="M833.6,31.7v12.7c1.1-1.7,3-3.5,6.8-3.5s7.8,2.4,7.8,8.9v14.8h-5.6v-14c0-3.3-1.3-5.1-4.2-5.1s-4.8,2.7-4.8,6.7v12.4h-5.6V31.7h5.6,0Z"/><path class="st2" d="M875.3,53c0,6.9-4.1,12.2-11.3,12.2s-11.1-5.1-11.1-12,4.2-12.2,11.4-12.2,11,4.8,11,12.1h0ZM858.6,53.1c0,4.7,2.1,7.7,5.6,7.7s5.5-2.9,5.5-7.6-2-7.7-5.5-7.7-5.5,2.6-5.5,7.7h0Z"/><path class="st2" d="M880,31.7h5.6v5.5h-5.6v-5.5ZM880,41.5h5.6v23.1h-5.6v-23.1Z"/><path class="st2" d="M911.2,57.3c-.9,3.9-3.6,7.9-10.1,7.9s-10.7-4.8-10.7-12,4-12.3,11-12.3,9.6,5.3,9.8,8h-5.5c-.5-2-1.7-3.6-4.4-3.6s-5.2,2.9-5.2,7.6,1.9,7.7,5.1,7.7,3.8-1.4,4.5-3.5c0,0,5.5,0,5.5,0Z"/><path class="st2" d="M919.8,54.2c0,3.5,1.8,6.5,5.2,6.5s3.9-1.3,4.6-2.8h5.6c-.8,2.9-3.4,7.3-10.3,7.3s-10.7-5.8-10.7-11.8,3.6-12.4,10.9-12.4,10.4,5.8,10.4,11.3,0,1.3,0,1.9h-15.6ZM929.9,50.7c0-3.1-1.4-5.6-4.8-5.6s-4.8,2.4-5.1,5.6h9.9Z"/></g><g><path class="st4" d="M551.1,89.8h7.4c4.1,0,5.9,2.1,5.9,4.7s-1.2,3.6-2.6,4.1c1.3.4,3.2,1.8,3.2,4.4s-2.7,5.5-6.3,5.5h-7.6v-18.8h0ZM558,97.6c2.6,0,3.6-1,3.6-2.8s-1.2-2.7-3.2-2.7h-4.5v5.5h4.2,0ZM553.8,106.3h4.5c2.3,0,3.8-1.1,3.8-3.3s-1.2-3.2-4.2-3.2h-4.1v6.5h0Z"/><path class="st4" d="M570,102.2c0,2.6,1.3,4.5,3.6,4.5s2.7-1,3.1-2h2.7c-.5,1.8-2,4.2-5.9,4.2s-6.2-3.5-6.2-7,2.1-7.3,6.3-7.3,6,3.5,6,6.6,0,.8,0,1.1h-9.6ZM577,100.4c0-2.1-1.1-3.9-3.3-3.9s-3.3,1.6-3.5,3.9h6.8Z"/><path class="st4" d="M581,94.8h2.3v-4h2.7v4h3v2.1h-3v7.8c0,1.3.3,1.9,1.5,1.9s.8,0,1.1,0v1.9c-.6.2-1.5.2-2.2.2-2.2,0-3.2-1.2-3.2-3.5v-8.3h-2.3v-2.1h0Z"/><path class="st4" d="M589.3,94.8h2.3v-4h2.7v4h3v2.1h-3v7.8c0,1.3.3,1.9,1.5,1.9s.8,0,1.1,0v1.9c-.6.2-1.5.2-2.2.2-2.2,0-3.2-1.2-3.2-3.5v-8.3h-2.3v-2.1h0Z"/><path class="st4" d="M601.3,102.2c0,2.6,1.3,4.5,3.6,4.5s2.7-1,3.1-2h2.7c-.5,1.8-2,4.2-5.9,4.2s-6.2-3.5-6.2-7,2.1-7.3,6.3-7.3,6,3.5,6,6.6,0,.8,0,1.1h-9.6ZM608.2,100.4c0-2.1-1.1-3.9-3.3-3.9s-3.3,1.6-3.5,3.9h6.8Z"/><path class="st4" d="M613.9,98.6c0-1.8,0-2.9,0-3.7h2.6c0,.4,0,1.5,0,2.7.7-1.8,2.3-3,4.6-3.1v2.6c-2.8,0-4.6,1.4-4.6,4.9v6.5h-2.7v-10h0Z"/><path class="st4" d="M630.8,89.8h7.2c3.7,0,6.2,2.1,6.2,5.5s-2.7,5.7-6.4,5.7h-4.3v7.6h-2.8s0-18.8,0-18.7ZM633.7,98.7h4.1c2.4,0,3.7-1.2,3.7-3.3s-1.5-3.3-3.6-3.3h-4.2v6.6h0Z"/><path class="st4" d="M647,98.6c0-1.8,0-2.9,0-3.7h2.6c0,.4,0,1.5,0,2.7.7-1.8,2.3-3,4.6-3.1v2.6c-2.8,0-4.6,1.4-4.6,4.9v6.5h-2.7v-10h0Z"/><path class="st4" d="M668.5,101.7c0,4-2.3,7.2-6.5,7.2s-6.4-3.1-6.4-7.2,2.4-7.2,6.6-7.2,6.4,2.9,6.4,7.2ZM658.3,101.7c0,2.9,1.4,5,3.8,5s3.7-2,3.7-5-1.3-5-3.8-5-3.7,1.9-3.7,5Z"/><path class="st4" d="M683.3,88.8v15.9c0,1.3,0,2.6,0,3.9h-2.5c0-.4-.1-1.4-.1-1.8-.7,1.4-2,2.2-4.2,2.2-3.5,0-5.7-2.8-5.7-7.1s2.3-7.3,6.2-7.3,3.4.9,3.8,1.7v-7.4h2.7,0ZM673.5,101.8c0,3.2,1.5,4.9,3.6,4.9,3.1,0,3.7-2.3,3.7-5.2s-.6-4.8-3.6-4.8-3.7,1.8-3.7,5h0Z"/><path class="st4" d="M698.5,104.6c0,1.3,0,3.1,0,4h-2.6c0-.4,0-1.2,0-2-.7,1.5-2,2.3-4.1,2.3s-4.5-1.3-4.5-5.1v-9h2.6v8.5c0,1.8.5,3.3,2.7,3.3s3.3-1.3,3.3-4.6v-7.2h2.7v9.7h0Z"/><path class="st4" d="M713.5,104.3c-.6,2.3-2.2,4.6-5.8,4.6s-6.2-2.8-6.2-7.1,2.2-7.3,6.4-7.3,5.4,2.9,5.6,4.7h-2.6c-.3-1.3-1.2-2.5-3-2.5s-3.6,2-3.6,5,1.3,5,3.5,5,2.6-.9,3.1-2.4h2.6Z"/><path class="st4" d="M714.9,94.8h2.3v-4h2.7v4h3v2.1h-3v7.8c0,1.3.3,1.9,1.5,1.9s.8,0,1.1,0v1.9c-.6.2-1.5.2-2.2.2-2.2,0-3.2-1.2-3.2-3.5v-8.3h-2.3v-2.1h0Z"/><path class="st4" d="M735.3,103.2l-1.9,5.4h-2.8l6.6-18.8h3.5l6.9,18.8h-3l-2-5.4h-7.4ZM742,100.8c-1.7-4.7-2.7-7.3-3.1-8.8h0c-.5,1.6-1.5,4.8-2.9,8.8h6Z"/><path class="st4" d="M747.7,94.8h2.3v-4h2.7v4h3v2.1h-3v7.8c0,1.3.3,1.9,1.5,1.9s.8,0,1.1,0v1.9c-.6.2-1.5.2-2.2.2-2.2,0-3.2-1.2-3.2-3.5v-8.3h-2.3v-2.1h0Z"/><path class="st4" d="M769.3,92.2h-6v-2.4h14.8v2.4h-6v16.4h-2.8v-16.4h0Z"/><path class="st4" d="M782.8,88.8v8c.7-1.2,1.9-2.3,4.2-2.3s4.6,1.3,4.6,5.3v8.8h-2.6v-8.3c0-2.2-.9-3.5-2.8-3.5s-3.4,1.6-3.4,4.3v7.5h-2.7v-19.8h2.7Z"/><path class="st4" d="M797.3,102.2c0,2.6,1.3,4.5,3.6,4.5s2.7-1,3.1-2h2.7c-.5,1.8-2,4.2-5.9,4.2s-6.2-3.5-6.2-7,2.1-7.3,6.3-7.3,6,3.5,6,6.6,0,.8,0,1.1h-9.6,0ZM804.3,100.4c0-2.1-1.1-3.9-3.3-3.9s-3.3,1.6-3.5,3.9h6.8Z"/><path class="st4" d="M819.7,100.5v8.1h-2.8v-18.8h7.5c3.9,0,6,2.1,6,5s-1.4,3.9-3.1,4.4c1.4.4,2.8,1.5,2.8,4.8v.8c0,1.3,0,2.9.3,3.7h-2.7c-.3-.8-.3-2.3-.3-4.1v-.4c0-2.6-.7-3.7-4-3.7,0,0-3.6,0-3.6,0ZM819.7,98.2h4c2.7,0,3.9-1,3.9-3s-1.2-3-3.7-3h-4.2v6h0Z"/><path class="st4" d="M833.9,88.8h2.7v3.1h-2.7v-3.1ZM833.9,94.8h2.7v13.7h-2.7v-13.7Z"/><path class="st4" d="M852,107.2c0,4.4-1.8,7-6.4,7s-5.6-2.4-5.8-4.2h2.7c.4,1.4,1.5,2.2,3.2,2.2,3,0,3.7-1.9,3.7-5v-1c-.7,1.5-2,2.3-4.2,2.3-3.4,0-5.7-2.8-5.7-6.8s2.6-7.2,6-7.2,3.7,1.2,4,2.1c0-.6,0-1.5.1-1.8h2.5c0,.9,0,2.6,0,4v8.5h0ZM842.3,101.6c0,2.8,1.4,4.7,3.6,4.7s3.7-1.9,3.7-4.8-.7-4.7-3.6-4.7-3.7,2-3.7,4.9h0Z"/><path class="st4" d="M858.6,88.8v8c.7-1.2,1.9-2.3,4.2-2.3s4.6,1.3,4.6,5.3v8.8h-2.6v-8.3c0-2.2-.9-3.5-2.8-3.5s-3.4,1.6-3.4,4.3v7.5h-2.7v-19.8h2.7Z"/><path class="st4" d="M869.5,94.8h2.3v-4h2.7v4h3v2.1h-3v7.8c0,1.3.3,1.9,1.5,1.9s.8,0,1.1,0v1.9c-.6.2-1.5.2-2.2.2-2.2,0-3.2-1.2-3.2-3.5v-8.3h-2.3v-2.1h0Z"/><path class="st4" d="M890,100.5v8.1h-2.8v-18.8h7.5c3.9,0,6,2.1,6,5s-1.4,3.9-3.1,4.4c1.4.4,2.8,1.5,2.8,4.8v.8c0,1.3,0,2.9.3,3.7h-2.7c-.3-.8-.3-2.3-.3-4.1v-.4c0-2.6-.7-3.7-4-3.7,0,0-3.6,0-3.6,0ZM890,98.2h4c2.7,0,3.9-1,3.9-3s-1.2-3-3.7-3h-4.2v6h0Z"/><path class="st4" d="M904.2,88.8h2.7v3.1h-2.7v-3.1ZM904.2,94.8h2.7v13.7h-2.7v-13.7Z"/><path class="st4" d="M921.9,104.3c-.6,2.3-2.2,4.6-5.8,4.6s-6.2-2.8-6.2-7.1,2.2-7.3,6.4-7.3,5.4,2.9,5.6,4.7h-2.6c-.3-1.3-1.2-2.5-3-2.5s-3.6,2-3.6,5,1.3,5,3.5,5,2.6-.9,3.1-2.4h2.6Z"/><path class="st4" d="M926.5,102.2c0,2.6,1.3,4.5,3.6,4.5s2.7-1,3.1-2h2.7c-.5,1.8-2,4.2-5.9,4.2s-6.2-3.5-6.2-7,2.1-7.3,6.3-7.3,6,3.5,6,6.6,0,.8,0,1.1h-9.6,0ZM933.4,100.4c0-2.1-1.1-3.9-3.3-3.9s-3.3,1.6-3.5,3.9h6.8Z"/></g></g><g><rect class="st1" x="540.3" y="133.2" width="396.8" height="13.6" rx="6.8" ry="6.8"/><rect class="st1" x="620.5" y="159.8" width="316.6" height="13.6" rx="6.8" ry="6.8"/></g><rect class="st3" x="776.8" y="208.9" width="160.3" height="59.3" rx="7" ry="7"/></g><path class="st3" d="M868.3,251.9c0,2-1.6,3.6-3.5,3.6s-3.5-1.6-3.5-3.6,1.6-3.6,3.5-3.6,3.5,1.6,3.5,3.6ZM850,248.3c-2,0-3.5,1.6-3.5,3.6s1.6,3.6,3.5,3.6,3.5-1.6,3.5-3.6-1.6-3.6-3.5-3.6ZM874.2,229.6c-.7-1-1.8-1.6-3-1.6h-23v-4.6c0-1-.8-1.8-1.8-1.8h-5.5c-1,0-1.8.8-1.8,1.8s.8,1.8,1.8,1.8h3.7v7.3c0,0,0,.1,0,.2v9c0,2,1.6,3.7,3.7,3.7h19c1.5,0,2.8-.9,3.4-2.3l4-10.1c.5-1.1.3-2.4-.4-3.4h0Z"/><g><rect class="st1" x="31.7" y="31.7" width="310" height="236.5" rx="21.4" ry="21.4"/><path class="st5" d="M161.3,129.6c-8.6,0-15.9-7.4-15.9-16.1,0-8.7,7.4-16,16-15.9,8.6,0,16.1,7.6,16,16.1,0,8.6-7.6,16-16.1,15.9h0ZM258,202.4c-23.7,0-47.4,0-71.1,0h0c-24.5,0-49,0-73.5,0-2.2,0-4.5,0-5.7-2.3-1.1-2.3-.2-4.4,1.2-6.3,9.4-13,18.7-26.1,28.2-39,4.1-5.5,9.1-5.4,13.3.2,2.5,3.4,4.9,6.9,7.5,10.3,4.3,5.6,9.9,5.7,14.2.1,2.1-2.6,4-5.4,5.9-8.1,7.9-10.8,15.7-21.7,23.6-32.5,2.6-3.6,6-4.7,9.5-3.2,2,.9,3.2,2.6,4.4,4.3,16,22.1,31.9,44.2,47.9,66.3,1.6,2.3,4,4.6,2.6,7.7-1.6,3.3-5,2.6-7.9,2.6h0Z"/></g></g></g></svg>'
                . '<span>'
                . __( 'This element is available in YayMail Pro', 'yaymail' )
                . '</span>',

            ],
            'position'        => 240,
            'data'            => [
                'padding'                     => [
                    'value_path'    => 'padding',
                    'component'     => 'Spacing',
                    'title'         => __( 'Padding', 'yaymail' ),
                    'default_value' => isset( $attributes['padding'] ) ? $attributes['padding'] : [
                        'top'    => '15',
                        'right'  => '50',
                        'bottom' => '15',
                        'left'   => '50',
                    ],
                    'type'          => 'style',
                ],
                'background_color'            => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'font_family'                 => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'rich_text'                   => [
                    'value_path'    => 'rich_text',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Content', 'yaymail' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : '<p style="text-align: right;"><strong><span style="color: #ffff00; font-size: 24px;">Your Elegance Our Choice</span></strong></p>
                    <p style="text-align: right;"><span style="color: #ffffff; font-size: 16px;"><strong><span style="color: #ffffff;"><span style="color: #ec4770;">BETTER PRODUCT AT THE RIGHT PRICE</span></span></strong></span></p>
                    <p style="text-align: right;"><span style="font-size: 14px; color: #010101;">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</span></p>',
                    'type'          => 'content',
                ],
                'content_breaker'             => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition'    => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'content_width'               => [
                    'value_path'    => 'content_width',
                    'component'     => 'Dimension',
                    'title'         => __( 'Content width', 'yaymail' ),
                    'default_value' => isset( $attributes['content_width'] ) ? $attributes['content_width'] : '60',
                    'type'          => 'style',
                ],
                'content_align'               => [
                    'value_path'    => 'content_align',
                    'component'     => 'Align',
                    'title'         => __( 'Content align', 'yaymail' ),
                    'default_value' => isset( $attributes['content_align'] ) ? $attributes['content_align'] : 'right',
                    'type'          => 'style',
                ],
                'showing_items'               => [
                    'value_path'    => 'showing_items',
                    'component'     => 'CheckboxGroup',
                    'title'         => __( 'Showing items', 'yaymail' ),
                    'default_value' => isset( $attributes['showing_items'] ) ? $attributes['showing_items'] : [ 'button', 'background_image' ],
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Background image', 'yaymail' ),
                            'value' => 'background_image',
                        ],
                        [
                            'label' => __( 'Button', 'yaymail' ),
                            'value' => 'button',
                        ],
                    ],
                ],
                'background_breaker'          => [
                    'component'  => 'LineBreaker',
                    'conditions' => $background_conditions,
                ],
                'background_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Background', 'yaymail' ),
                    'description' => __( 'Handle background settings', 'yaymail' ),
                    'conditions'  => $background_conditions,
                ],
                'background_image'            => [
                    'value_path'    => 'background_image',
                    'component'     => 'BackgroundImage',
                    'title'         => __( 'Background image', 'yaymail' ),
                    'default_value' => isset( $attributes['background_image'] ) ? $attributes['background_image'] : [
                        'url'        => YAYMAIL_PLUGIN_URL . 'assets/images/shopping-image.jpeg',
                        'position'   => 'center_center',
                        'x_position' => 0,
                        'y_position' => 0,
                        'repeat'     => 'default',
                        'size'       => 'cover',
                    ],
                    'type'          => 'style',
                    'conditions'    => $background_conditions,
                ],
                'button_breaker'              => [
                    'component'  => 'LineBreaker',
                    'conditions' => $button_conditions,
                ],
                'button_group_definition'     => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Button', 'yaymail' ),
                    'description' => __( 'Handle button settings', 'yaymail' ),
                    'conditions'  => $button_conditions,
                ],
                'button_text'                 => [
                    'value_path'    => 'button_text',
                    'component'     => 'TextInput',
                    'title'         => __( 'Button text', 'yaymail' ),
                    'default_value' => isset( $attributes['button_text'] ) ? $attributes['button_text'] : __( 'ORDER NOW', 'yaymail' ),
                    'type'          => 'content',
                    'conditions'    => $button_conditions,
                ],
                'button_url'                  => [
                    'value_path'    => 'button_url',
                    'component'     => 'TextInput',
                    'title'         => __( 'Button URL', 'yaymail' ),
                    'default_value' => isset( $attributes['button_url'] ) ? $attributes['button_url'] : home_url(),
                    'type'          => 'content',
                    'conditions'    => $button_conditions,
                ],
                'button_align'                => [
                    'value_path'    => 'button_align',
                    'component'     => 'Align',
                    'title'         => __( 'Button align', 'yaymail' ),
                    'default_value' => isset( $attributes['button_align'] ) ? $attributes['button_align'] : 'right',
                    'type'          => 'style',
                    'conditions'    => $button_conditions,
                ],
                'button_background_color'     => [
                    'value_path'    => 'button_background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Button background color', 'yaymail' ),
                    'default_value' => isset( $attributes['button_background_color'] ) ? $attributes['button_background_color'] : '#ec4770',
                    'type'          => 'style',
                    'conditions'    => $button_conditions,
                ],
                'button_text_color'           => [
                    'value_path'    => 'button_text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Button text color', 'yaymail' ),
                    'default_value' => isset( $attributes['button_text_color'] ) ? $attributes['button_text_color'] : '#fff',
                    'type'          => 'style',
                    'conditions'    => $button_conditions,
                ],
            ],
        ];
    }
}
