<?php

namespace YayMail\Models;

use YayMail\Utils\SingletonTrait;

/**
 * Product Model
 *
 * @method static ProductModel get_instance()
 */
class ProductModel {

    use SingletonTrait;

    const DEFAULT_LIMIT = 5;

    const COMMON_WP_QUERY_ARGUMENTS = [
        'post_type'   => 'product',
        'post_status' => 'publish',
    ];

    /**
     * Retrieves a list of terms(categories | tags | products) based on the provided parameters.
     *
     * This function fetches a list of terms(categories | tags | products) based on the specified search criteria and pagination options.
     *
     * @param array $params An associative array of parameters for the term retrieval.
     *   - 'search_string' (string): The search string to filter terms. Default is an empty string.
     *   - 'page_num' (number): The page number for paginating results. Default is "1".
     *   - 'page_size' (number): The number of terms to retrieve per page. Default is "20".
     *   - 'term_type' (string): The type of terms to retrieve. Could be "product_cat" | "product_tag" | null | ''. (null | '' is for Product).
     * @param array $field_mapping An associative array of field mapping for the term retrieval.
     *   - 'id' (string): The field name for the term ID. Default is "id".
     *   - 'name' (string): The field name for the term name. Default is "name".
     *
     * @return array An associative array containing the retrieved terms.
     *   - 'list' (array): An array of term data, each with 'id' and 'name' fields.
     *   - 'next_page' (number|false): The token for the next page of results, if available.
     */
    public function get_terms( $params, $field_mapping = [
        'id'   => 'id',
        'name' => 'name',
    ] ) {
        $page_data = $this->get_terms_page( isset( $params['term_type'] ) ? $params['term_type'] : '', $params['search_string'] ?? '', $params['page_num'] ?? 1, $params['page_size'] ?? 20 );

        $result = [
            'list'      => array_map(
                function( $item ) use ( $field_mapping ) {
                    $id_field   = $field_mapping['id'] ?? 'id';
                    $name_field = $field_mapping['name'] ?? 'name';
                    return [
                        'id'   => strval( isset( $item->{$id_field} ) ? $item->{$id_field} : $item->id ),
                        'name' => isset( $item->{$name_field} ) ? $item->{$name_field} : $item->name,
                    ];
                },
                $page_data['list']
            ),
            'next_page' => $page_data['next_page'],
        ];

        return $result;
    }

    /**
     * Retrieves featured products based on the provided parameters and product type.
     *
     * This function allows you to retrieve featured products based on different product types or specific criteria. It delegates the retrieval of products to various specialized methods depending on the product type.

     * @param array $params An associative array of parameters for retrieving featured products.
     *   - 'product_type' (string): The type of featured products to retrieve (e.g., 'newest', 'on_sale', 'featured', 'category_selections', 'tag_selections', 'product_selections'). Default is 'newest'.
     *   - 'number_of_products' (string): The number of featured products to retrieve. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for the featured products. Default is "none".
     *   - 'category_ids' (null or array): An array of category IDs to filter products by, or null if not used.
     *   - 'tag_ids' (null or array): An array of tag IDs to filter products by, or null if not used.
     *   - 'product_ids' (null or array): An array of product IDs to retrieve, or null if not used.
     *
     * @return array An array of featured products with details in the following format:
     *   - 'id' (int): The product's ID.
     *   - 'name' (string): The product's name.
     *   - 'sale_price_html' (string): The HTML representation of the sale price.
     *   - 'regular_price_html' (string): The HTML representation of the regular price.
     *   - 'thumbnail_src' (string): The URL of the product's thumbnail image.
     *   - 'permalink' (string): The URL to the product's page.
     */
    public function get_featured_products( $params ) {
        $product_type = isset( $params['product_type'] ) ? $params['product_type'] : 'newest';
        unset( $params['product_type'] );

        switch ( $product_type ) {
            case 'newest':
                $products = $this->get_newest_products( $params );
                break;
            case 'on_sale':
                $products = $this->get_on_sale_products( $params );
                break;
            case 'featured':
                $products = $this->get_product_type_featured_products( $params );
                break;
            case 'category_selections':
                $products = $this->get_by_categories( $params );
                break;
            case 'tag_selections':
                $products = $this->get_by_tags( $params );
                break;
            case 'product_selections':
                $products = $this->get_by_product_ids( $params );
                break;
            default:
                $products = [];
                break;
        }//end switch

        $products_response = array_map( [ $this, 'get_product_response' ], $products );

        $result = [];

        foreach ( $products_response as $product_response ) {
            if ( ! empty( $product_response ) ) {
                $result[] = $product_response;
            }
        }

        if ( isset( $params['sorted_by'] ) && 'price_ascending' === $params['sorted_by'] ) {
            usort(
                $result,
                function( $a, $b ) {
                    return (float) $a['price'] - (float) $b['price'];
                }
            );
        }
        if ( isset( $params['sorted_by'] ) && 'price_descending' === $params['sorted_by'] ) {
            usort(
                $result,
                function( $a, $b ) {
                    return (float) $b['price'] - (float) $a['price'];
                }
            );
        }
        return $result;
    }

    public function get_cross_up_sells_products( $params ) {
        $order_id               = isset( $params['order_id'] ) ? $params['order_id'] : 0;
        $linked_products_type   = isset( $params['linked_products_type'] ) ? $params['linked_products_type'] : 'cross_sells';
        $max_products_displayed = isset( $params['max_products_displayed'] ) ? $params['max_products_displayed'] : 0;
        if ( 0 === $order_id ) {
            return [];
        }

        if ( 'sample_order' === $order_id ) {
            $products          = wc_get_products( [ 'limit' => $max_products_displayed ] );
            $products_response = array_map( [ $this, 'get_product_response' ], $products );
            return $products_response;
        }

        $order       = wc_get_order( $order_id );
        $items       = $order->get_items();
        $product_ids = [];
        $products    = [];
        foreach ( $items as $item ) {
            if ( 'cross_sells' === $linked_products_type ) {
                $product_ids = array_merge( $item->get_product()->get_cross_sell_ids(), $product_ids );
            } else {
                $product_ids = array_merge( $item->get_product()->get_upsell_ids(), $product_ids );
            }
        }
        $product_ids = array_unique( $product_ids );

        if ( empty( $product_ids ) ) {
            return [];
        }

        foreach ( $product_ids as $product_id ) {
            if ( count( $products ) < $max_products_displayed ) {
                $products[] = wc_get_product( $product_id );
            }
        }

        $products_response = array_map( [ $this, 'get_product_response' ], $products );

        return $products_response;
    }

    /**
     * Retrieves the newest products based on the provided criteria.
     *
     * This function retrieves the newest products from the WooCommerce store based on the specified criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving the newest products.
     *   - 'number_of_products' (string): The number of newest products to retrieve. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for the newest products. Default is "none".
     *   - 'category_ids' (null or array): An array of category IDs to filter products by, or null if not used.
     *   - 'tag_ids' (null or array): An array of tag IDs to filter products by, or null if not used.
     *   - 'product_ids' (null or array): An array of specific product IDs to retrieve, or null if not used.
     *
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing the newest products.
     */
    private function get_newest_products( $criteria, $optional_args = [] ) {
        $args = [
            'limit'     => isset( $criteria['number_of_products'] ) ? $criteria['number_of_products'] : self::DEFAULT_LIMIT,

            'orderby'   => 'date',
            'order'     => 'DESC',
            'status'    => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'grouped',
                    'operator' => 'NOT IN',
                ],
            ],
        ];
        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query    = new \WC_Product_Query( $args );
        $products = $query->get_products();

        return $products;
    }

    /**
     * Retrieves products on sale based on the provided criteria.
     *
     * This function retrieves products on sale from the WooCommerce store based on the specified criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving products on sale.
     *   - 'number_of_products' (string): The number of products on sale to retrieve. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for products on sale. Default is "none".
     *   - 'category_ids' (null or array): An array of category IDs to filter products by, or null if not used.
     *   - 'tag_ids' (null or array): An array of tag IDs to filter products by, or null if not used.
     *   - 'product_ids' (null or array): An array of specific product IDs to retrieve, or null if not used.
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing products on sale.
     */
    private function get_on_sale_products( $criteria, $optional_args = [] ) {
        $args = [
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => '_sale_price',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'numeric',
                ],
                [
                    'key'     => '_min_variation_sale_price',
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'numeric',
                ],

            ],
            'status'     => 'publish',
            'tax_query'  => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'grouped',
                    'operator' => 'NOT IN',
                ],
            ],
        ];

        $args = array_merge(
            self::COMMON_WP_QUERY_ARGUMENTS,
            [
                'posts_per_page' => isset( $criteria['number_of_products'] ) ? $criteria['number_of_products'] : self::DEFAULT_LIMIT,
                'fields'         => 'ids',
            ],
            $args
        );

        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query       = new \WP_QUERY( $args );
        $product_ids = $query->posts;

        $products = wc_get_products( [ 'include' => $product_ids ] );
        return $products;
    }

    /**
     * Retrieves featured products based on the provided criteria.
     *
     * This function retrieves products on sale from the WooCommerce store based on the specified criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving products on sale.
     *   - 'number_of_products' (string): The number of products on sale to retrieve. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for products on sale. Default is "none".
     *   - 'category_ids' (null or array): An array of category IDs to filter products by, or null if not used.
     *   - 'tag_ids' (null or array): An array of tag IDs to filter products by, or null if not used.
     *   - 'product_ids' (null or array): An array of specific product IDs to retrieve, or null if not used.
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing products on sale.
     */
    private function get_product_type_featured_products( $criteria, $optional_args = [] ) {
        $tax_query[] = [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => 'featured',
            'operator' => 'IN',
        ];
        $tax_query[] = [
            'taxonomy' => 'product_type',
            'field'    => 'slug',
            'terms'    => 'grouped',
            'operator' => 'NOT IN',
        ];

        $args = array_merge(
            self::COMMON_WP_QUERY_ARGUMENTS,
            [
                'posts_per_page' => isset( $criteria['number_of_products'] ) ? $criteria['number_of_products'] : self::DEFAULT_LIMIT,
                'fields'         => 'ids',
                'status'         => 'publish',
                'tax_query'      => $tax_query,
            ]
        );

        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query = new \WP_QUERY( $args );
        if ( $query->have_posts() ) {
            $product_ids = $query->posts;
            $products    = wc_get_products( [ 'include' => $product_ids ] );
            return $products;
        } else {
            return [];
        }
    }

    /**
     * Retrieves products by category IDs based on the provided criteria.
     *
     * This function retrieves products from the WooCommerce store based on specified category IDs and criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving products by category.
     *   - 'number_of_products' (string): The number of products to retrieve by category. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for products by category. Default is "none".
     *   - 'category_ids' (array): An array of category IDs to filter products by. If empty, an empty array is returned.
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing products by category.
     */
    private function get_by_categories( $criteria, $optional_args = [] ) {
        if ( empty( $criteria['category_ids'] ) ) {
            return [];
        }

        $args = [
            'limit'               => isset( $criteria['number_of_products'] ) ? $criteria['number_of_products'] : self::DEFAULT_LIMIT,
            'product_category_id' => $criteria['category_ids'],
            'status'              => 'publish',
        ];

        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query    = new \WC_Product_Query( $args );
        $products = $query->get_products();
        return $products;
    }

    /**
     * Retrieves products by tag IDs based on the provided criteria.
     *
     * This function retrieves products from the WooCommerce store based on specified tag IDs and criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving products by tag.
     *   - 'number_of_products' (string): The number of products to retrieve by tag. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for products by tag. Default is "none".
     *   - 'tag_ids' (array): An array of category IDs to filter products by. If empty, an empty array is returned.
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing products by category.
     */
    private function get_by_tags( $criteria, $optional_args = [] ) {
        if ( empty( $criteria['tag_ids'] ) ) {
            return [];
        }

        $args = [
            'limit'          => isset( $criteria['number_of_products'] ) ? $criteria['number_of_products'] : self::DEFAULT_LIMIT,
            'product_tag_id' => $criteria['tag_ids'],
            'status'         => 'publish',
        ];

        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query    = new \WC_Product_Query( $args );
        $products = $query->get_products();
        return $products;
    }

    /**
     * Retrieves products by specific product IDs based on the provided criteria.
     *
     * This function retrieves products from the WooCommerce store based on specified product IDs and criteria, including the number of products to retrieve and the sorting order.
     *
     * @param array $criteria An associative array of criteria for retrieving products by specific product IDs.
     *   - 'number_of_products' (string): The number of products to retrieve by specific product IDs. Default is "5".
     *   - 'sorted_by' (string): The sorting criteria for products by specific product IDs. Default is "none".
     *   - 'product_ids' (array): An array of specific product IDs to retrieve. If empty, an empty array is returned.
     * @param array $optional_args An associative array of optional arguments for the query.
     *
     * @return WC_Product_Simple[]|WC_Product_Variable[] An array of WooCommerce simple and variable product objects representing products by specific product IDs.
     */
    private function get_by_product_ids( $criteria, $optional_args = [] ) {
        if ( empty( $criteria['product_ids'] ) ) {
            return [];
        }

        $args = [
            'limit'     => -1,
            'include'   => $criteria['product_ids'],
            'status'    => 'publish',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => 'grouped',
                    'operator' => 'NOT IN',
                ],
            ],
        ];

        if ( isset( $criteria['sorted_by'] ) && 'random' === $criteria['sorted_by'] ) {
            $args['orderby'] = 'rand';
        }

        if ( ! empty( $optional_args ) ) {
            $args = wp_parse_args( $args, $optional_args );
        }

        $query    = new \WC_Product_Query( $args );
        $products = $query->get_products();
        return $products;
    }

    /**
     * Retrieves a page of terms (categories, tags, or products) based on the provided parameters.
     *
     * This function retrieves a page of terms, which can be categories, tags, or products, based on the specified taxonomy, search string, page number, and page size.
     *
     * @param string $taxonomy The taxonomy to filter terms (categories or tags) or an empty string for products.
     * @param string $search_string The search string to filter terms or products by name. Default is an empty string.
     * @param int    $page_num The page number for paginating results. Default is 1.
     * @param int    $page_size The number of terms to retrieve per page. Default is 10.
     * @param array  $optional_args Optional WP_Query arguments to merge with default arguments.
     *
     * @return array An associative array containing the retrieved terms or products.
     *   - 'list' (array): An array of term or product data, each with 'id' and 'name' fields.
     *   - 'next_page' (int|false): The page number for the next page of results, or false if no more pages are available.
     */
    private function get_terms_page( $taxonomy, $search_string = '', $page_num = 1, $page_size = 10, $optional_args = [] ) {

        // if ( class_exists( 'SitePress' ) ) {
        // do_action( 'wpml_switch_language', $active_language );
        // }
        $limit = $page_size + 1;
        // +1 in order to check for next_page
        $offset = ( $page_num - 1 ) * $page_size;

        if ( empty( $taxonomy ) ) {
            /**
             * Get products
             */
            global $wpdb;
            $query = $wpdb->prepare(
                "SELECT id, post_title AS name
                FROM {$wpdb->prefix}posts
                WHERE {$wpdb->prefix}posts.post_type = 'product'
                AND {$wpdb->prefix}posts.post_title LIKE %s 
                ORDER BY post_title ASC
                LIMIT %d OFFSET %d",
                "%{$search_string}%",
                $limit,
                $offset
            );
            $list  = $wpdb->get_results( $query );

        } else {
            /**
             * Get categories or tags
             */
            $orderby      = 'name';
            $show_count   = 0;
            $pad_counts   = 0;
            $hierarchical = 1;
            $empty        = 0;

            $args = [
                'taxonomy'     => $taxonomy,
                'orderby'      => $orderby,
                'show_count'   => $show_count,
                'pad_counts'   => $pad_counts,
                'hierarchical' => $hierarchical,
                'hide_empty'   => $empty,
                'number'       => $limit,
                'offset'       => $offset,
            ];

            if ( ! empty( $search_string ) ) {
                $args['name__like'] = $search_string;
            }

            $list = array_values( \get_categories( $args ) );
        }//end if

        $next_page = count( $list ) > $page_size ? $page_num + 1 : false;

        if ( $next_page ) {
            array_pop( $list );
        }

        $result = [
            'list'      => $list,
            'next_page' => $next_page,
        ];

        return $result;
    }
}
