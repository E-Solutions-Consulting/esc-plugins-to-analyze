<?php
namespace YayMail\Elements;

use YayMail\Abstracts\BaseElement;
use YayMail\Utils\SingletonTrait;

/**
 * Simple Offer Elements
 */
class SimpleOffer extends BaseElement {

    use SingletonTrait;

    protected static $type = 'simple_offer';

    public $available_email_ids = [];

    public static function get_data( $attributes = [] ) {
        self::$icon = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 20 20">
  <path d="M17.56,3.61H7.3c-.27,0-.45.09-.63.27l-.27.27-.27-.27c-.18-.18-.36-.27-.63-.27h-3.06c-.81,0-1.44.63-1.44,1.35v9.99c0,.81.63,1.44,1.44,1.44h3.06c.27,0,.45-.09.63-.27l.27-.27.27.27c.18.18.45.27.63.27h10.26c.81,0,1.44-.63,1.44-1.44V4.96c0-.72-.63-1.35-1.44-1.35ZM17.65,14.86c0,.09-.09.18-.18.18H7.48l-.54-.54v-1.71h-1.17v1.71l-.54.54h-2.7c-.09,0-.18-.09-.18-.18V5.14c0-.09.09-.18.18-.18h2.7l.54.54v1.71h1.08v-1.71l.54-.54h9.99c.09,0,.18.09.18.18v9.72h.09ZM5.77,10.54h1.08v1.08h-1.08v-1.08ZM5.77,8.29h1.08v1.08h-1.08v-1.08ZM10.27,9.55c.9,0,1.71-.72,1.71-1.71,0-.9-.72-1.71-1.71-1.71-.9,0-1.71.72-1.71,1.71.09.99.81,1.71,1.71,1.71ZM10.27,7.3c.36,0,.63.27.63.63s-.27.54-.54.54-.54-.27-.54-.54c-.09-.36.09-.63.45-.63ZM15.49,7.03l-5.58,6.75c-.09.09-.27.18-.45.18-.09,0-.27,0-.36-.09-.27-.18-.27-.54-.09-.81l5.58-6.75c.18-.18.54-.27.72-.09.36.18.36.54.18.81ZM14.23,10.45c-.9,0-1.71.72-1.71,1.71,0,.9.72,1.71,1.71,1.71s1.71-.72,1.71-1.71-.72-1.71-1.71-1.71ZM14.23,12.7c-.27,0-.54-.27-.54-.54s.27-.54.54-.54.54.27.54.54c.09.27-.18.54-.54.54Z"/>
</svg>';

        $border_conditions = [
            [
                'comparison' => 'contain',
                'value'      => [ 'border' ],
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
            'name'            => __( 'Simple Offer', 'yaymail' ),
            'icon'            => self::$icon,
            'group'           => 'block',
            'available'       => false,
            'disabled_reason' => [
                'html' => '<svg id="yaymail-simple_offer" class="yaymail-element-disabled-icon" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1000 300"><defs><style> #yaymail-simple_offer .st0 { fill: #fff; } #yaymail-simple_offer .st1 { fill: #1f2937; } #yaymail-simple_offer .st2 { fill: #dee2e9; } #yaymail-simple_offer .st3 { fill: #6b7280; } </style></defs><g id="Layer_1-2"><g><rect class="st0" width="1000" height="300"/><g><path class="st1" d="M103.5,90.3h-24.9v14.8h27.4l-1.2,8h-35.6v-51.3h35.4v8h-26v12.6h24.9v8h0Z"/><path class="st1" d="M120.7,74.9c4.4,7.6,6.3,10.7,7.5,12.7h0c1.4-2.4,4-6.8,7.6-12.7h10.3l-12.6,18.2,12.6,19.9h-10.5c-3.8-6.8-6.3-11.3-7.6-13.6h0c-1.1,2.1-3.9,7-7.6,13.6h-10.1l12.2-18.8-12-19.4h10.3,0Z"/><path class="st1" d="M148.8,74.9h6.1v-10.6h9.2v10.6h7.8v7.1h-7.8v20c0,3.2.9,4.6,4.1,4.6s1.8,0,2.7-.2v6.5c-1.9.7-4.6.7-6.6.7-6.9,0-9.4-3.7-9.4-10.2v-21.3h-6.1v-7.1h0Z"/><path class="st1" d="M178.2,85.3c0-4.3,0-7.6,0-10.3h9.1c.1.9.2,4.4.2,7.1,1.8-4.6,6-7.9,12.4-7.9v8.9c-7.7,0-12.4,2.7-12.4,12.9v17.3h-9.2v-27.8Z"/><path class="st1" d="M235.7,103.8c0,3.6.3,8.4.5,9.3h-8.8c-.3-.8-.5-2.7-.6-4.2-1.4,2.3-4.1,5-11.1,5s-13-6-13-11.9c0-8.6,6.9-12.6,18.1-12.6h5.8v-2.6c0-2.9-1-6.1-6.7-6.1s-6.1,2.3-6.7,5.2h-8.8c.6-6.3,4.4-11.9,15.8-11.9,9.9,0,15.3,4,15.3,12.9v16.8h0ZM226.7,95.3h-4.9c-6.8,0-9.7,2-9.7,6.2s2,5.7,6.1,5.7c7.5,0,8.5-5.2,8.5-10.8v-1.1Z"/><path class="st1" d="M262.8,76.5c.7-6,5-13.1,16.6-13.1s15.8,6.3,15.8,13.1-3.4,8.8-6.2,10v.2c3.6,1.2,8.2,4.5,8.2,12s-4.5,15.4-17.9,15.4-17-7.6-17.6-14.1h9c.8,3.7,3.4,6.9,8.9,6.9s8.2-3.7,8.2-8.3-2.9-8-8.9-8h-4.1v-7.2h3.5c5.4,0,7.6-1.9,7.6-6.4s-2.3-6.2-6.9-6.2-6.8,2.9-7.5,5.8h-8.8Z"/><path class="st1" d="M340.2,88.4c0,13-4,25.6-18.2,25.6s-18.2-12.1-18.2-25.3,4.6-25.3,18.4-25.3,18,11.6,18,25h0ZM313.4,88.6c0,9.1,1.2,17.9,8.7,17.9s8.5-8.3,8.5-18.1-1.3-17.6-8.4-17.6-8.9,7.1-8.9,17.8h0Z"/><path class="st1" d="M372,77.3c0,8.2-4.6,14.1-13,14.1s-12.9-5.1-12.9-14.1,4.5-14,13-14,12.9,4.9,12.9,14h0ZM353.5,77.3c0,5.1,2,8.1,5.7,8.1s5.7-2.7,5.7-8.1-2.2-7.9-5.8-7.9-5.6,2.6-5.6,7.9h0ZM358.7,113.1l30.6-48.9h7l-30.6,48.9h-7ZM408.8,100c0,8.2-4.6,14.1-13,14.1s-12.9-5.2-12.9-14.1,4.5-14,13-14,12.9,4.9,12.9,14h0ZM390.2,100c0,5.1,2,8.1,5.7,8.1s5.7-2.7,5.7-8.1-2.2-7.9-5.8-7.9-5.6,2.7-5.6,7.9h0Z"/><path class="st1" d="M469,93.9c0,11.4-6.7,20-18.6,20s-18.3-8.4-18.3-19.9,6.9-20.1,18.8-20.1,18.2,7.9,18.2,19.9h0ZM441.4,94c0,7.7,3.5,12.6,9.2,12.6s9-4.8,9-12.6-3.3-12.7-9.1-12.7-9,4.3-9,12.6Z"/><path class="st1" d="M487.4,82v31.1h-9.2v-31.1h-5.8v-7.1h5.8v-3.8c0-7.6,3.1-13.1,12-13.1s3.1.2,4.1.5v7.3c-.9-.2-1.8-.3-2.7-.3-3.1,0-4.1,2-4.1,5.7v3.7h12.5v-3.8c0-7.6,3.2-13.1,12-13.1s3.1.2,4.1.5v7.3c-.9-.2-1.8-.3-2.7-.3-3.1,0-4.1,2-4.1,5.7v3.7h6.9v7.1h-6.9v31.1h-9.2v-31.1h-12.5,0Z"/></g><g><path class="st3" d="M94.5,168.5c0,8.6-4.9,16.2-14.8,16.2s-14.4-7.1-14.4-16.1,5.3-16.1,14.8-16.1,14.4,6.6,14.4,15.9h0ZM70.1,168.6c0,6.6,3.3,12.3,9.8,12.3s9.8-5.8,9.8-12.3-3.1-12.1-9.8-12.1-9.8,5.5-9.8,12h0Z"/><path class="st3" d="M100,167.2c0-2,0-4,0-5.8h4.3c.1.7.2,2.9.2,3.6.9-1.9,2.8-4.1,7.1-4.1s7.6,2.7,7.6,8.8v14.7h-4.4v-14.2c0-3.3-1.3-5.5-4.6-5.5s-5.7,2.9-5.7,7.4v12.2h-4.4v-17.1h0Z"/><path class="st3" d="M165.3,168.5c0,8.6-4.9,16.2-14.8,16.2s-14.4-7.1-14.4-16.1,5.3-16.1,14.8-16.1,14.4,6.6,14.4,15.9h0ZM140.9,168.6c0,6.6,3.3,12.3,9.8,12.3s9.8-5.8,9.8-12.3-3.1-12.1-9.8-12.1-9.8,5.5-9.8,12h0Z"/><path class="st3" d="M170.8,167.6c0-3,0-4.9,0-6.2h4.3c0,.6.1,2.5.1,4.6,1.2-3,3.8-5,7.6-5.1v4.4c-4.7,0-7.6,2.3-7.6,8.2v10.9h-4.4v-16.7h0Z"/><path class="st3" d="M206.1,151.3v26.6c0,2.1,0,4.2,0,6.4h-4.2c-.1-.6-.2-2.3-.2-3.1-1.1,2.3-3.4,3.6-7.1,3.6-5.9,0-9.5-4.8-9.5-11.8s3.9-12.2,10.3-12.2,5.7,1.6,6.3,2.8v-12.3h4.4ZM189.6,173c0,5.3,2.5,8.2,6,8.2,5.2,0,6.2-3.9,6.2-8.6s-1-8-5.9-8-6.2,3.1-6.2,8.4h0Z"/><path class="st3" d="M215.5,173.7c0,4.3,2.2,7.5,5.9,7.5s4.5-1.7,5.2-3.3h4.4c-.9,2.9-3.4,7-9.8,7s-10.3-5.8-10.3-11.7,3.5-12.3,10.5-12.3,9.9,5.9,9.9,10.9,0,1.3,0,1.9h-15.9,0ZM227,170.7c0-3.6-1.8-6.4-5.5-6.4s-5.5,2.7-5.9,6.4h11.4,0Z"/><path class="st3" d="M236.4,167.6c0-3,0-4.9,0-6.2h4.3c0,.6.1,2.5.1,4.6,1.2-3,3.8-5,7.6-5.1v4.4c-4.7,0-7.6,2.3-7.6,8.2v10.9h-4.4v-16.7h0Z"/><path class="st3" d="M255.2,177.7c.6,2.4,2.4,3.8,5.4,3.8s4.4-1.3,4.4-3.3-1-2.9-5.2-3.9c-6.7-1.6-8.1-3.7-8.1-6.8s2.4-6.5,8.5-6.5,8.5,3.5,8.8,6.7h-4.2c-.3-1.4-1.3-3.3-4.7-3.3s-3.9,1.4-3.9,2.8.9,2.4,5,3.4c7.1,1.7,8.4,4.1,8.4,7.4s-3.1,7-9.1,7-9-3.1-9.6-7.2h4.4,0Z"/><path class="st3" d="M291,175.4l-3.2,9h-4.6l11-31.2h5.7l11.5,31.2h-4.9l-3.3-9h-12.3ZM302.1,171.4c-2.8-7.9-4.4-12.2-5.1-14.7h0c-.8,2.7-2.5,7.9-4.8,14.7h10Z"/><path class="st3" d="M315.6,151.3h4.4v13.2c1-1.9,3.1-3.6,7.2-3.6,5.8,0,9.4,4.8,9.4,11.5s-3.5,12.4-10,12.4-5.5-1.3-6.6-3.4c0,1.1,0,2.2-.2,2.9h-4.2c0-2.5,0-5.1,0-7.6v-25.4ZM332,172.5c0-4.7-2.1-7.9-6-7.9s-6.2,3.1-6.2,8.4,1.5,8.2,6.1,8.2,6.1-3.4,6.1-8.7h0Z"/><path class="st3" d="M361.8,172.8c0,6.7-3.9,12-10.9,12s-10.7-5.1-10.7-11.9,4-12,10.9-12,10.6,4.8,10.6,11.9ZM344.7,172.9c0,4.9,2.4,8.4,6.3,8.4s6.2-3.3,6.2-8.4-2.2-8.4-6.3-8.4-6.2,3.2-6.2,8.4h0Z"/><path class="st3" d="M368.7,161.4c2.9,9.1,5.1,16,5.7,18.9h0c.6-2.8,3-9.7,6.1-18.9h4.5l-8.4,22.9h-4.8l-8-22.9h4.7Z"/><path class="st3" d="M391.5,173.7c0,4.3,2.2,7.5,5.9,7.5s4.5-1.7,5.2-3.3h4.4c-.9,2.9-3.4,7-9.8,7s-10.3-5.8-10.3-11.7,3.5-12.3,10.5-12.3,10,5.9,10,10.9,0,1.3,0,1.9h-16,0ZM403,170.7c0-3.6-1.8-6.4-5.5-6.4s-5.5,2.7-5.9,6.4h11.4Z"/><path class="st3" d="M425.7,176.6c.6,3.1,3.1,4.6,6.1,4.6s5.8-1.6,5.8-4.6-1.3-4.2-6.2-5.6c-6.9-1.9-9.4-4.3-9.4-8.6s4.1-8,9.5-8,9.6,4.3,10,7.5h-4.3c-.5-2.1-1.8-4.1-5.7-4.1s-5,1.9-5,4.1,1.3,3.9,6.3,5.2c7.3,2,9.4,5,9.4,9.1s-4,8.5-10.4,8.5c-6.2,0-9.8-3.4-10.3-8.1h4.4,0ZM430.4,188.6v-37.2h2.7v37.2h-2.7Z"/><path class="st3" d="M451.7,184.3v-24.4c-1.5,1-4.4,2.1-6,2.3v-3.5c2.4-.6,5.2-2.3,7.2-4.2h3.3v29.7h-4.5Z"/><path class="st3" d="M483.9,169.3c0,7.5-2.2,15.6-10.5,15.6s-10.5-7.6-10.5-15.3,2.5-15.3,10.7-15.3,10.4,7.4,10.4,15.1h0ZM467.5,169.5c0,5.6.9,11.7,5.9,11.7s5.9-5.8,5.9-11.8-.9-11.5-5.8-11.5-6,5.1-6,11.6Z"/><path class="st3" d="M509.6,169.3c0,7.5-2.2,15.6-10.5,15.6s-10.5-7.6-10.5-15.3,2.5-15.3,10.7-15.3,10.4,7.4,10.4,15.1h0ZM493.1,169.5c0,5.6.9,11.7,5.9,11.7s5.9-5.8,5.9-11.8-.9-11.5-5.8-11.5-6,5.1-6,11.6Z"/><path class="st3" d="M94.5,222.5c0,8.6-4.9,16.2-14.8,16.2s-14.4-7.1-14.4-16.1,5.3-16.1,14.8-16.1,14.4,6.6,14.4,15.9h0ZM70.1,222.6c0,6.6,3.3,12.3,9.8,12.3s9.8-5.8,9.8-12.3-3.1-12.1-9.8-12.1-9.8,5.5-9.8,12h0Z"/><path class="st3" d="M100,221.2c0-2,0-4,0-5.8h4.3c.1.7.2,2.9.2,3.6.9-1.9,2.8-4.1,7.1-4.1s7.6,2.7,7.6,8.8v14.7h-4.4v-14.2c0-3.3-1.3-5.5-4.6-5.5s-5.7,2.9-5.7,7.4v12.2h-4.4v-17.1h0Z"/><path class="st3" d="M137.7,238.3v-31.2h6c4.4,7.2,13.6,22.2,15.3,25.6h0c-.3-3.9-.3-8.9-.3-14v-11.6h4.3v31.2h-5.7c-4-6.7-13.7-23-15.6-26.5h0c.2,3.4.2,8.9.2,14.6v11.9h-4.4Z"/><path class="st3" d="M173.2,227.7c0,4.3,2.2,7.5,5.9,7.5s4.5-1.7,5.2-3.3h4.4c-.9,2.9-3.4,7-9.8,7s-10.3-5.8-10.3-11.7,3.5-12.3,10.5-12.3,9.9,5.9,9.9,10.9,0,1.3,0,1.9h-15.9,0ZM184.8,224.7c0-3.6-1.8-6.4-5.5-6.4s-5.5,2.7-5.9,6.4h11.4,0Z"/><path class="st3" d="M196.6,215.4c2.7,10.4,4.2,16.4,4.6,18.9h0c.4-2.2,1.9-7.5,5-18.9h4.3c3.4,12.6,4.4,16.3,4.8,18.5h0c.6-2.4,1.4-5.9,4.8-18.5h4.4l-7,22.9h-4.8c-2.2-8.4-4.1-15.5-4.6-18.1h0c-.4,2.6-2.3,9.2-4.8,18.1h-4.9l-6.6-22.9h4.7,0Z"/><path class="st3" d="M245.6,229.4l-3.2,9h-4.6l11-31.2h5.7l11.5,31.2h-4.9l-3.3-9h-12.3,0ZM256.8,225.4c-2.8-7.9-4.4-12.2-5.1-14.7h0c-.8,2.7-2.5,7.9-4.8,14.7h10Z"/><path class="st3" d="M270.3,221.6c0-3,0-4.9,0-6.2h4.3c0,.6.1,2.5.1,4.6,1.2-3,3.8-5,7.6-5.1v4.4c-4.7,0-7.6,2.3-7.6,8.2v10.9h-4.4v-16.7h0Z"/><path class="st3" d="M286.1,221.6c0-3,0-4.9,0-6.2h4.3c0,.6.1,2.5.1,4.6,1.2-3,3.8-5,7.6-5.1v4.4c-4.7,0-7.6,2.3-7.6,8.2v10.9h-4.4v-16.7h0Z"/><path class="st3" d="M302,205.3h4.4v5.1h-4.4v-5.1ZM302,215.4h4.4v22.9h-4.4v-22.9Z"/><path class="st3" d="M315.1,215.4c2.9,9.1,5.1,16,5.7,18.9h0c.6-2.8,3-9.7,6.1-18.9h4.5l-8.4,22.9h-4.8l-8-22.9h4.7Z"/><path class="st3" d="M352.1,233.1c0,2.1.2,4.5.3,5.2h-4.2c-.2-.5-.3-1.6-.4-2.9-.8,1.5-2.6,3.4-7,3.4s-7.7-3.5-7.7-7c0-5.1,4-7.5,10.8-7.5h3.8v-1.8c0-1.9-.6-4.2-4.7-4.2s-4.2,1.8-4.6,3.5h-4.2c.3-3.3,2.4-6.9,9-6.9,5.7,0,8.9,2.3,8.9,7.6v10.7h0ZM347.8,227.3h-3.5c-4.4,0-6.7,1.2-6.7,4.3s1.5,3.9,4.2,3.9c5.2,0,5.9-3.5,5.9-7.5v-.7h0Z"/><path class="st3" d="M358.7,238.3v-33h4.4v33h-4.4Z"/><path class="st3" d="M371.9,231.7c.6,2.4,2.4,3.8,5.4,3.8s4.4-1.3,4.4-3.3-1-2.9-5.2-3.9c-6.7-1.6-8.1-3.7-8.1-6.8s2.4-6.5,8.5-6.5,8.5,3.5,8.8,6.7h-4.2c-.3-1.4-1.3-3.3-4.7-3.3s-3.9,1.4-3.9,2.8.9,2.4,5,3.4c7.1,1.7,8.4,4.1,8.4,7.4s-3.1,7-9.1,7-9-3.1-9.6-7.2h4.4,0Z"/></g><rect class="st2" x="658.9" y="98.5" width="278.2" height="103" rx="9" ry="9"/><path class="st2" d="M813.2,173.2c0,3.4-2.8,6.2-6.2,6.2s-6.2-2.8-6.2-6.2,2.8-6.2,6.2-6.2,6.2,2.8,6.2,6.2ZM781.5,167c-3.4,0-6.2,2.8-6.2,6.2s2.8,6.2,6.2,6.2,6.2-2.8,6.2-6.2-2.8-6.2-6.2-6.2ZM823.5,134.6c-1.2-1.8-3.2-2.8-5.3-2.8h-39.9v-8c0-1.8-1.4-3.2-3.2-3.2h-9.5c-1.8,0-3.2,1.4-3.2,3.2s1.4,3.2,3.2,3.2h6.4v12.7c0,.1,0,.2,0,.4v15.6c0,3.5,2.8,6.4,6.4,6.4h32.9c2.6,0,4.9-1.6,5.9-4l7-17.5c.8-2,.5-4.2-.6-5.9h0Z"/></g></g></svg>'
                . '<span>'
                . __( 'This element is available in YayMail Pro', 'yaymail' )
                . '</span>',
            ],
            'position'        => 230,
            'data'            => [
                'padding'                  => [
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
                'background_color'         => [
                    'value_path'    => 'background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Background color', 'yaymail' ),
                    'default_value' => isset( $attributes['background_color'] ) ? $attributes['background_color'] : '#fff',
                    'type'          => 'style',
                ],
                'text_color'               => [
                    'value_path'    => 'text_color',
                    'component'     => 'Color',
                    'title'         => __( 'Text color', 'yaymail' ),
                    'default_value' => isset( $attributes['text_color'] ) ? $attributes['text_color'] : YAYMAIL_COLOR_TEXT_DEFAULT,
                    'type'          => 'style',
                ],
                'font_family'              => [
                    'value_path'    => 'font_family',
                    'component'     => 'FontFamilySelector',
                    'title'         => __( 'Font family', 'yaymail' ),
                    'default_value' => isset( $attributes['font_family'] ) ? $attributes['font_family'] : YAYMAIL_DEFAULT_FAMILY,
                    'type'          => 'style',
                ],
                'content_breaker'          => [
                    'component' => 'LineBreaker',
                ],
                'content_group_definition' => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Content', 'yaymail' ),
                    'description' => __( 'Handle content settings', 'yaymail' ),
                ],
                'rich_text'                => [
                    'value_path'    => 'rich_text',
                    'component'     => 'RichTextEditor',
                    'title'         => __( 'Content', 'yaymail' ),
                    'default_value' => isset( $attributes['rich_text'] ) ? $attributes['rich_text'] : '<p style="text-align: left;"><span style="font-size: 30px; color: #ec4770;"><strong>Extra 30% off</strong></span></p>
                    <p style="text-align: left;"><span style="font-size: 18px;"><strong>ON ORDERS ABOVE $100 ON NEW ARRIVALS</strong></span></p>',
                    'type'          => 'content',
                ],
                'showing_items'            => [
                    'value_path'    => 'showing_items',
                    'component'     => 'CheckboxGroup',
                    'title'         => __( 'Showing items', 'yaymail' ),
                    'default_value' => isset( $attributes['showing_items'] ) ? $attributes['showing_items'] : [ 'button', 'border' ],
                    'type'          => 'content',
                    'options'       => [
                        [
                            'label' => __( 'Border', 'yaymail' ),
                            'value' => 'border',
                        ],
                        [
                            'label' => __( 'Button', 'yaymail' ),
                            'value' => 'button',
                        ],
                    ],
                ],
                'border_breaker'           => [
                    'component'  => 'LineBreaker',
                    'conditions' => $border_conditions,
                ],
                'border_group_definition'  => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Border', 'yaymail' ),
                    'description' => __( 'Handle content border settings', 'yaymail' ),
                    'conditions'  => $border_conditions,
                ],
                'border_width'             => [
                    'value_path'    => 'border_width',
                    'component'     => 'NumberInput',
                    'title'         => __( 'Border width', 'yaymail' ),
                    'default_value' => isset( $attributes['border_width'] ) ? $attributes['border_width'] : '3',
                    'min'           => 0,
                    'max'           => 100,
                    'type'          => 'style',
                    'conditions'    => $border_conditions,
                ],
                'border_style'             => [
                    'value_path'    => 'border_style',
                    'component'     => 'Selector',
                    'title'         => __( 'Border style', 'yaymail' ),
                    'default_value' => isset( $attributes['border_style'] ) ? $attributes['border_style'] : 'solid',
                    'options'       => [
                        [
                            'label' => __( 'Solid', 'yaymail' ),
                            'value' => 'solid',
                        ],
                        [
                            'label' => __( 'Double', 'yaymail' ),
                            'value' => 'double',
                        ],
                        [
                            'label' => __( 'Dotted', 'yaymail' ),
                            'value' => 'dotted',
                        ],
                        [
                            'label' => __( 'Dashed', 'yaymail' ),
                            'value' => 'dashed',
                        ],
                    ],
                    'type'          => 'style',
                    'conditions'    => $border_conditions,
                ],
                'border_color'             => [
                    'value_path'    => 'border_color',
                    'component'     => 'Color',
                    'title'         => __( 'Border color', 'yaymail' ),
                    'default_value' => isset( $attributes['border_color'] ) ? $attributes['border_color'] : '#000000',
                    'type'          => 'style',
                    'conditions'    => $border_conditions,
                ],
                'button_breaker'           => [
                    'component'  => 'LineBreaker',
                    'conditions' => $button_conditions,
                ],
                'button_group_definition'  => [
                    'component'   => 'GroupDefinition',
                    'title'       => __( 'Button', 'yaymail' ),
                    'description' => __( 'Handle button settings', 'yaymail' ),
                    'conditions'  => $button_conditions,
                ],
                'button_text'              => [
                    'value_path'    => 'button_text',
                    'component'     => 'TextInput',
                    'title'         => __( 'Button text', 'yaymail' ),
                    'default_value' => isset( $attributes['button_text'] ) ? $attributes['button_text'] : __( 'ORDER NOW', 'yaymail' ),
                    'type'          => 'content',
                    'conditions'    => $button_conditions,
                ],
                'button_url'               => [
                    'value_path'    => 'button_url',
                    'component'     => 'TextInput',
                    'title'         => __( 'Button URL', 'yaymail' ),
                    'default_value' => isset( $attributes['button_url'] ) ? $attributes['button_url'] : home_url(),
                    'type'          => 'content',
                    'conditions'    => $button_conditions,
                ],
                'button_background_color'  => [
                    'value_path'    => 'button_background_color',
                    'component'     => 'Color',
                    'title'         => __( 'Button background color', 'yaymail' ),
                    'default_value' => isset( $attributes['button_background_color'] ) ? $attributes['button_background_color'] : '#ec4770',
                    'type'          => 'style',
                    'conditions'    => $button_conditions,
                ],
                'button_text_color'        => [
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
