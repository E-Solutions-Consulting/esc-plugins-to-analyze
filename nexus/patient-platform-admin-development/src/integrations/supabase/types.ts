export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[]

export type Database = {
  // Allows to automatically instantiate createClient with right options
  // instead of createClient<Database, { PostgrestVersion: 'XX' }>(URL, KEY)
  __InternalSupabase: {
    PostgrestVersion: "14.1"
  }
  public: {
    Tables: {
      admin_users: {
        Row: {
          auth_user_id: string
          avatar_url: string | null
          created_at: string
          email: string
          full_name: string | null
          id: string
          is_active: boolean
          updated_at: string
        }
        Insert: {
          auth_user_id: string
          avatar_url?: string | null
          created_at?: string
          email: string
          full_name?: string | null
          id?: string
          is_active?: boolean
          updated_at?: string
        }
        Update: {
          auth_user_id?: string
          avatar_url?: string | null
          created_at?: string
          email?: string
          full_name?: string | null
          id?: string
          is_active?: boolean
          updated_at?: string
        }
        Relationships: []
      }
      audit_logs: {
        Row: {
          action: string
          actor_email: string | null
          actor_id: string | null
          after_data: Json | null
          before_data: Json | null
          created_at: string
          diff: Json | null
          entity_id: string | null
          entity_type: string
          id: string
          ip_address: unknown
          request_id: string | null
          tenant_id: string | null
          user_agent: string | null
        }
        Insert: {
          action: string
          actor_email?: string | null
          actor_id?: string | null
          after_data?: Json | null
          before_data?: Json | null
          created_at?: string
          diff?: Json | null
          entity_id?: string | null
          entity_type: string
          id?: string
          ip_address?: unknown
          request_id?: string | null
          tenant_id?: string | null
          user_agent?: string | null
        }
        Update: {
          action?: string
          actor_email?: string | null
          actor_id?: string | null
          after_data?: Json | null
          before_data?: Json | null
          created_at?: string
          diff?: Json | null
          entity_id?: string | null
          entity_type?: string
          id?: string
          ip_address?: unknown
          request_id?: string | null
          tenant_id?: string | null
          user_agent?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "audit_logs_actor_id_fkey"
            columns: ["actor_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "audit_logs_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      exclusive_flag_options: {
        Row: {
          display_order: number
          feature_flag_id: string
          id: string
          set_id: string
        }
        Insert: {
          display_order?: number
          feature_flag_id: string
          id?: string
          set_id: string
        }
        Update: {
          display_order?: number
          feature_flag_id?: string
          id?: string
          set_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "exclusive_flag_options_feature_flag_id_fkey"
            columns: ["feature_flag_id"]
            isOneToOne: false
            referencedRelation: "feature_flags"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "exclusive_flag_options_set_id_fkey"
            columns: ["set_id"]
            isOneToOne: false
            referencedRelation: "exclusive_flag_sets"
            referencedColumns: ["id"]
          },
        ]
      }
      exclusive_flag_sets: {
        Row: {
          created_at: string
          description: string | null
          id: string
          name: string
        }
        Insert: {
          created_at?: string
          description?: string | null
          id?: string
          name: string
        }
        Update: {
          created_at?: string
          description?: string | null
          id?: string
          name?: string
        }
        Relationships: []
      }
      feature_flags: {
        Row: {
          created_at: string
          default_value: boolean
          description: string | null
          flag_type: Database["public"]["Enums"]["flag_type"]
          id: string
          is_active: boolean
          key: string
          name: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          default_value?: boolean
          description?: string | null
          flag_type?: Database["public"]["Enums"]["flag_type"]
          id?: string
          is_active?: boolean
          key: string
          name: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          default_value?: boolean
          description?: string | null
          flag_type?: Database["public"]["Enums"]["flag_type"]
          id?: string
          is_active?: boolean
          key?: string
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      medication_capabilities: {
        Row: {
          created_at: string
          description: string | null
          display_order: number
          id: string
          is_active: boolean
          key: string
          name: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          description?: string | null
          display_order?: number
          id?: string
          is_active?: boolean
          key: string
          name: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          description?: string | null
          display_order?: number
          id?: string
          is_active?: boolean
          key?: string
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      medication_capability_assignments: {
        Row: {
          capability_id: string
          created_at: string
          id: string
          medication_id: string
        }
        Insert: {
          capability_id: string
          created_at?: string
          id?: string
          medication_id: string
        }
        Update: {
          capability_id?: string
          created_at?: string
          id?: string
          medication_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "medication_capability_assignments_capability_id_fkey"
            columns: ["capability_id"]
            isOneToOne: false
            referencedRelation: "medication_capabilities"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "medication_capability_assignments_medication_id_fkey"
            columns: ["medication_id"]
            isOneToOne: false
            referencedRelation: "medications"
            referencedColumns: ["id"]
          },
        ]
      }
      medication_shot_intakes: {
        Row: {
          created_at: string
          dosage_strength: number
          id: string
          injection_site_id: string | null
          intake_date: string
          medication_id: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          pain_level: number
          patient_id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          dosage_strength: number
          id?: string
          injection_site_id?: string | null
          intake_date: string
          medication_id: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          pain_level: number
          patient_id: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          dosage_strength?: number
          id?: string
          injection_site_id?: string | null
          intake_date?: string
          medication_id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          pain_level?: number
          patient_id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "medication_shot_intakes_injection_site_id_fkey"
            columns: ["tenant_id", "injection_site_id"]
            isOneToOne: false
            referencedRelation: "tenant_injection_site_definitions"
            referencedColumns: ["tenant_id", "id"]
          },
          {
            foreignKeyName: "medication_shot_intakes_medication_id_fkey"
            columns: ["medication_id"]
            isOneToOne: false
            referencedRelation: "medications"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "medication_shot_intakes_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "medication_shot_intakes_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      medications: {
        Row: {
          created_at: string
          description: string | null
          form: string
          id: string
          image_url: string | null
          is_enabled: boolean
          legal_ready: Database["public"]["Enums"]["readiness_status"]
          metadata: Json | null
          offering_id: string | null
          partner_compound_id: string | null
          pharmacy_approval_id: string | null
          pharmacy_ready: Database["public"]["Enums"]["readiness_status"]
          provider_approval_id: string | null
          provider_sku: string | null
          tenant_id: string
          title: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          description?: string | null
          form?: string
          id?: string
          image_url?: string | null
          is_enabled?: boolean
          legal_ready?: Database["public"]["Enums"]["readiness_status"]
          metadata?: Json | null
          offering_id?: string | null
          partner_compound_id?: string | null
          pharmacy_approval_id?: string | null
          pharmacy_ready?: Database["public"]["Enums"]["readiness_status"]
          provider_approval_id?: string | null
          provider_sku?: string | null
          tenant_id: string
          title: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          description?: string | null
          form?: string
          id?: string
          image_url?: string | null
          is_enabled?: boolean
          legal_ready?: Database["public"]["Enums"]["readiness_status"]
          metadata?: Json | null
          offering_id?: string | null
          partner_compound_id?: string | null
          pharmacy_approval_id?: string | null
          pharmacy_ready?: Database["public"]["Enums"]["readiness_status"]
          provider_approval_id?: string | null
          provider_sku?: string | null
          tenant_id?: string
          title?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "medications_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      order_items: {
        Row: {
          created_at: string
          id: string
          order_id: string
          product_id: string
          quantity: number
          total_cents: number
          unit_price_cents: number
        }
        Insert: {
          created_at?: string
          id?: string
          order_id: string
          product_id: string
          quantity?: number
          total_cents?: number
          unit_price_cents?: number
        }
        Update: {
          created_at?: string
          id?: string
          order_id?: string
          product_id?: string
          quantity?: number
          total_cents?: number
          unit_price_cents?: number
        }
        Relationships: [
          {
            foreignKeyName: "order_items_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: true
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_items_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
        ]
      }
      order_payment_provider_transactions: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          order_id: string
          paid_at: string | null
          payment_provider_id: string
          payment_status: string | null
          provider_charge_id: string | null
          provider_checkout_session_id: string | null
          provider_customer_id: string | null
          provider_invoice_id: string | null
          provider_payment_intent_id: string | null
          provider_subscription_id: string | null
          subscription_id: string | null
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          order_id: string
          paid_at?: string | null
          payment_provider_id: string
          payment_status?: string | null
          provider_charge_id?: string | null
          provider_checkout_session_id?: string | null
          provider_customer_id?: string | null
          provider_invoice_id?: string | null
          provider_payment_intent_id?: string | null
          provider_subscription_id?: string | null
          subscription_id?: string | null
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          order_id?: string
          paid_at?: string | null
          payment_provider_id?: string
          payment_status?: string | null
          provider_charge_id?: string | null
          provider_checkout_session_id?: string | null
          provider_customer_id?: string | null
          provider_invoice_id?: string | null
          provider_payment_intent_id?: string | null
          provider_subscription_id?: string | null
          subscription_id?: string | null
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "order_payment_provider_transactions_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_payment_provider_transactions_payment_provider_id_fkey"
            columns: ["payment_provider_id"]
            isOneToOne: false
            referencedRelation: "payment_providers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_payment_provider_transactions_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_payment_provider_transactions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      order_pharmacy_platform_links: {
        Row: {
          created_at: string
          id: string
          latest_order_status: string | null
          latest_rx_status: string | null
          lifefile_fill_id: string | null
          lifefile_order_id: string
          metadata: Json | null
          order_id: string
          rx_number: string | null
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          latest_order_status?: string | null
          latest_rx_status?: string | null
          lifefile_fill_id?: string | null
          lifefile_order_id: string
          metadata?: Json | null
          order_id: string
          rx_number?: string | null
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          latest_order_status?: string | null
          latest_rx_status?: string | null
          lifefile_fill_id?: string | null
          lifefile_order_id?: string
          metadata?: Json | null
          order_id?: string
          rx_number?: string | null
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "order_pharmacy_platform_links_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_pharmacy_platform_links_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      order_provider_platform_links: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          order_id: string
          provider_order_id: string | null
          tenant_id: string
          tenant_integration_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          order_id: string
          provider_order_id?: string | null
          tenant_id: string
          tenant_integration_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          order_id?: string
          provider_order_id?: string | null
          tenant_id?: string
          tenant_integration_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "order_provider_platform_links_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_provider_platform_links_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_provider_platform_links_tenant_integration_id_fkey"
            columns: ["tenant_integration_id"]
            isOneToOne: false
            referencedRelation: "tenant_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      order_status_history: {
        Row: {
          changed_by: string | null
          changed_by_email: string | null
          created_at: string
          id: string
          notes: string | null
          order_id: string
          status_id: string
        }
        Insert: {
          changed_by?: string | null
          changed_by_email?: string | null
          created_at?: string
          id?: string
          notes?: string | null
          order_id: string
          status_id: string
        }
        Update: {
          changed_by?: string | null
          changed_by_email?: string | null
          created_at?: string
          id?: string
          notes?: string | null
          order_id?: string
          status_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "order_status_history_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_status_history_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_status_history_status_id_fkey"
            columns: ["status_id"]
            isOneToOne: false
            referencedRelation: "order_statuses"
            referencedColumns: ["id"]
          },
        ]
      }
      order_statuses: {
        Row: {
          admin_microcopy: string | null
          admin_status_label: string
          created_at: string
          display_order: number
          expiration_timer_hours: number | null
          failure_status_id: string | null
          id: string
          is_active: boolean
          is_patient_visible: boolean
          is_terminal: boolean
          next_status_id: string | null
          next_step_owner: string
          patient_action_required: boolean
          patient_microcopy: string | null
          patient_status_label: string | null
          status_key: string
          updated_at: string
        }
        Insert: {
          admin_microcopy?: string | null
          admin_status_label: string
          created_at?: string
          display_order?: number
          expiration_timer_hours?: number | null
          failure_status_id?: string | null
          id?: string
          is_active?: boolean
          is_patient_visible?: boolean
          is_terminal?: boolean
          next_status_id?: string | null
          next_step_owner?: string
          patient_action_required?: boolean
          patient_microcopy?: string | null
          patient_status_label?: string | null
          status_key: string
          updated_at?: string
        }
        Update: {
          admin_microcopy?: string | null
          admin_status_label?: string
          created_at?: string
          display_order?: number
          expiration_timer_hours?: number | null
          failure_status_id?: string | null
          id?: string
          is_active?: boolean
          is_patient_visible?: boolean
          is_terminal?: boolean
          next_status_id?: string | null
          next_step_owner?: string
          patient_action_required?: boolean
          patient_microcopy?: string | null
          patient_status_label?: string | null
          status_key?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "order_statuses_failure_status_id_fkey"
            columns: ["failure_status_id"]
            isOneToOne: false
            referencedRelation: "order_statuses"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "order_statuses_next_status_id_fkey"
            columns: ["next_status_id"]
            isOneToOne: false
            referencedRelation: "order_statuses"
            referencedColumns: ["id"]
          },
        ]
      }
      orders: {
        Row: {
          billing_address_line1: string | null
          billing_address_line2: string | null
          billing_city: string | null
          billing_company: string | null
          billing_country: string | null
          billing_first_name: string | null
          billing_last_name: string | null
          billing_postal_code: string | null
          billing_state: string | null
          cancellation_operation_completed_at: string | null
          cancellation_operation_key: string | null
          cancellation_operation_started_at: string | null
          cancellation_reason: string | null
          cancelled_at: string | null
          coupon_code: string | null
          coupon_name: string | null
          created_at: string
          delivered_at: string | null
          discount_cents: number | null
          id: string
          idv_locked_at: string | null
          internal_notes: string | null
          order_number: string
          paid_at: string | null
          patient_id: string
          paused_at: string | null
          payment_failed_at: string | null
          payment_retry_count: number
          product_id: string | null
          provider_platform_integration_key: string
          provider_platform_order_id: string | null
          renewal_at: string | null
          shipped_at: string | null
          shipping_address_line1: string | null
          shipping_address_line2: string | null
          shipping_cents: number
          shipping_city: string | null
          shipping_company: string | null
          shipping_country: string | null
          shipping_first_name: string | null
          shipping_instructions: string | null
          shipping_last_name: string | null
          shipping_postal_code: string | null
          shipping_state: string | null
          status_changed_at: string | null
          status_id: string | null
          stripe_charge_id: string | null
          stripe_checkout_session_id: string | null
          stripe_invoice_id: string | null
          stripe_payment_intent_id: string | null
          stripe_payment_status: string | null
          stripe_subscription_id: string | null
          subscription_id: string | null
          subscription_order_type: string | null
          subtotal_cents: number
          tax_cents: number
          tenant_id: string
          total_cents: number
          tracking_number: string | null
          tracking_url: string | null
          updated_at: string
        }
        Insert: {
          billing_address_line1?: string | null
          billing_address_line2?: string | null
          billing_city?: string | null
          billing_company?: string | null
          billing_country?: string | null
          billing_first_name?: string | null
          billing_last_name?: string | null
          billing_postal_code?: string | null
          billing_state?: string | null
          cancellation_operation_completed_at?: string | null
          cancellation_operation_key?: string | null
          cancellation_operation_started_at?: string | null
          cancellation_reason?: string | null
          cancelled_at?: string | null
          coupon_code?: string | null
          coupon_name?: string | null
          created_at?: string
          delivered_at?: string | null
          discount_cents?: number | null
          id?: string
          idv_locked_at?: string | null
          internal_notes?: string | null
          order_number: string
          paid_at?: string | null
          patient_id: string
          paused_at?: string | null
          payment_failed_at?: string | null
          payment_retry_count?: number
          product_id?: string | null
          provider_platform_integration_key?: string
          provider_platform_order_id?: string | null
          renewal_at?: string | null
          shipped_at?: string | null
          shipping_address_line1?: string | null
          shipping_address_line2?: string | null
          shipping_cents?: number
          shipping_city?: string | null
          shipping_company?: string | null
          shipping_country?: string | null
          shipping_first_name?: string | null
          shipping_instructions?: string | null
          shipping_last_name?: string | null
          shipping_postal_code?: string | null
          shipping_state?: string | null
          status_changed_at?: string | null
          status_id?: string | null
          stripe_charge_id?: string | null
          stripe_checkout_session_id?: string | null
          stripe_invoice_id?: string | null
          stripe_payment_intent_id?: string | null
          stripe_payment_status?: string | null
          stripe_subscription_id?: string | null
          subscription_id?: string | null
          subscription_order_type?: string | null
          subtotal_cents?: number
          tax_cents?: number
          tenant_id: string
          total_cents?: number
          tracking_number?: string | null
          tracking_url?: string | null
          updated_at?: string
        }
        Update: {
          billing_address_line1?: string | null
          billing_address_line2?: string | null
          billing_city?: string | null
          billing_company?: string | null
          billing_country?: string | null
          billing_first_name?: string | null
          billing_last_name?: string | null
          billing_postal_code?: string | null
          billing_state?: string | null
          cancellation_operation_completed_at?: string | null
          cancellation_operation_key?: string | null
          cancellation_operation_started_at?: string | null
          cancellation_reason?: string | null
          cancelled_at?: string | null
          coupon_code?: string | null
          coupon_name?: string | null
          created_at?: string
          delivered_at?: string | null
          discount_cents?: number | null
          id?: string
          idv_locked_at?: string | null
          internal_notes?: string | null
          order_number?: string
          paid_at?: string | null
          patient_id?: string
          paused_at?: string | null
          payment_failed_at?: string | null
          payment_retry_count?: number
          product_id?: string | null
          provider_platform_integration_key?: string
          provider_platform_order_id?: string | null
          renewal_at?: string | null
          shipped_at?: string | null
          shipping_address_line1?: string | null
          shipping_address_line2?: string | null
          shipping_cents?: number
          shipping_city?: string | null
          shipping_company?: string | null
          shipping_country?: string | null
          shipping_first_name?: string | null
          shipping_instructions?: string | null
          shipping_last_name?: string | null
          shipping_postal_code?: string | null
          shipping_state?: string | null
          status_changed_at?: string | null
          status_id?: string | null
          stripe_charge_id?: string | null
          stripe_checkout_session_id?: string | null
          stripe_invoice_id?: string | null
          stripe_payment_intent_id?: string | null
          stripe_payment_status?: string | null
          stripe_subscription_id?: string | null
          subscription_id?: string | null
          subscription_order_type?: string | null
          subtotal_cents?: number
          tax_cents?: number
          tenant_id?: string
          total_cents?: number
          tracking_number?: string | null
          tracking_url?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "orders_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "orders_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "orders_status_id_fkey"
            columns: ["status_id"]
            isOneToOne: false
            referencedRelation: "order_statuses"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "orders_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "orders_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_activity_entries: {
        Row: {
          activity_definition_id: string | null
          activity_label: string
          created_at: string
          id: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          patient_id: string
          recorded_at: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          activity_definition_id?: string | null
          activity_label: string
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id: string
          recorded_at?: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          activity_definition_id?: string | null
          activity_label?: string
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id?: string
          recorded_at?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_activity_entries_activity_definition_id_fkey"
            columns: ["activity_definition_id"]
            isOneToOne: false
            referencedRelation: "tenant_activity_definitions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_activity_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_activity_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_energy_entries: {
        Row: {
          created_at: string
          energy_value: number
          id: string
          patient_id: string
          recorded_at: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          energy_value: number
          id?: string
          patient_id: string
          recorded_at?: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          energy_value?: number
          id?: string
          patient_id?: string
          recorded_at?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_energy_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_energy_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_mood_change_entries: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          mood_change_definition_id: string | null
          mood_change_label: string
          patient_id: string
          recorded_at: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          mood_change_definition_id?: string | null
          mood_change_label: string
          patient_id: string
          recorded_at?: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          mood_change_definition_id?: string | null
          mood_change_label?: string
          patient_id?: string
          recorded_at?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_mood_change_entries_mood_change_definition_id_fkey"
            columns: ["mood_change_definition_id"]
            isOneToOne: false
            referencedRelation: "tenant_mood_change_definitions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_mood_change_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_mood_change_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_mood_entries: {
        Row: {
          created_at: string
          id: string
          mood_label: string | null
          mood_note: string | null
          mood_value: number
          patient_id: string
          recorded_at: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          mood_label?: string | null
          mood_note?: string | null
          mood_value: number
          patient_id: string
          recorded_at?: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          mood_label?: string | null
          mood_note?: string | null
          mood_value?: number
          patient_id?: string
          recorded_at?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_mood_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_mood_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_password_reset_tokens: {
        Row: {
          auth_user_id: string
          created_at: string
          expires_at: string
          id: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          patient_id: string
          redirect_url: string
          tenant_id: string
          token_hash: string
          updated_at: string
          used_at: string | null
        }
        Insert: {
          auth_user_id: string
          created_at?: string
          expires_at: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id: string
          redirect_url: string
          tenant_id: string
          token_hash: string
          updated_at?: string
          used_at?: string | null
        }
        Update: {
          auth_user_id?: string
          created_at?: string
          expires_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id?: string
          redirect_url?: string
          tenant_id?: string
          token_hash?: string
          updated_at?: string
          used_at?: string | null
        }
        Relationships: [
          {
            foreignKeyName: "patient_password_reset_tokens_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_password_reset_tokens_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_platform_terms_acceptances: {
        Row: {
          accepted_at: string
          created_at: string
          id: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          patient_id: string
          platform_terms_version: number
          platform_terms_version_id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          accepted_at: string
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id: string
          platform_terms_version: number
          platform_terms_version_id: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          accepted_at?: string
          created_at?: string
          id?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id?: string
          platform_terms_version?: number
          platform_terms_version_id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_platform_terms_acceptanc_platform_terms_version_id_fkey"
            columns: ["platform_terms_version_id"]
            isOneToOne: false
            referencedRelation: "platform_terms_versions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_platform_terms_acceptances_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_platform_terms_acceptances_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_privacy_policy_acceptances: {
        Row: {
          accepted_at: string
          created_at: string
          id: string
          patient_id: string
          privacy_policy_version: number
          privacy_policy_version_id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          accepted_at: string
          created_at?: string
          id?: string
          patient_id: string
          privacy_policy_version: number
          privacy_policy_version_id: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          accepted_at?: string
          created_at?: string
          id?: string
          patient_id?: string
          privacy_policy_version?: number
          privacy_policy_version_id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_privacy_policy_acceptanc_privacy_policy_version_id_fkey"
            columns: ["privacy_policy_version_id"]
            isOneToOne: false
            referencedRelation: "privacy_policy_versions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_privacy_policy_acceptances_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_privacy_policy_acceptances_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_provider_platform_links: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          patient_id: string
          provider_patient_id: string | null
          tenant_id: string
          tenant_integration_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id: string
          provider_patient_id?: string | null
          tenant_id: string
          tenant_integration_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id?: string
          provider_patient_id?: string | null
          tenant_id?: string
          tenant_integration_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_provider_platform_links_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_provider_platform_links_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_provider_platform_links_tenant_integration_id_fkey"
            columns: ["tenant_integration_id"]
            isOneToOne: false
            referencedRelation: "tenant_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_symptom_entries: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          patient_id: string
          recorded_at: string
          symptom_definition_id: string | null
          symptom_label: string
          symptom_note: string | null
          symptom_severity: number | null
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id: string
          recorded_at?: string
          symptom_definition_id?: string | null
          symptom_label: string
          symptom_note?: string | null
          symptom_severity?: number | null
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id?: string
          recorded_at?: string
          symptom_definition_id?: string | null
          symptom_label?: string
          symptom_note?: string | null
          symptom_severity?: number | null
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_symptom_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_symptom_entries_symptom_definition_id_fkey"
            columns: ["symptom_definition_id"]
            isOneToOne: false
            referencedRelation: "tenant_symptom_definitions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_symptom_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_terms_acceptances: {
        Row: {
          accepted_at: string
          accepted_content: string | null
          created_at: string
          id: string
          patient_id: string
          product_id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          accepted_at: string
          accepted_content?: string | null
          created_at?: string
          id?: string
          patient_id: string
          product_id: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          accepted_at?: string
          accepted_content?: string | null
          created_at?: string
          id?: string
          patient_id?: string
          product_id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "patient_terms_acceptances_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_terms_acceptances_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_terms_acceptances_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_weight_entries: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          patient_id: string
          tenant_id: string
          updated_at: string
          weighed_at: string
          weight_unit: string
          weight_value: number
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id: string
          tenant_id: string
          updated_at?: string
          weighed_at?: string
          weight_unit?: string
          weight_value: number
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          patient_id?: string
          tenant_id?: string
          updated_at?: string
          weighed_at?: string
          weight_unit?: string
          weight_value?: number
        }
        Relationships: [
          {
            foreignKeyName: "patient_weight_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_weight_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patient_body_measurement_entries: {
        Row: {
          arms_inches: number
          chest_inches: number
          created_at: string
          hips_inches: number
          id: string
          measured_at: string
          metadata: Json
          migration_source: string | null
          migration_source_id: string | null
          migration_source_item_key: string | null
          patient_id: string
          tenant_id: string
          updated_at: string
          waist_inches: number
        }
        Insert: {
          arms_inches: number
          chest_inches: number
          created_at?: string
          hips_inches: number
          id?: string
          measured_at?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id: string
          tenant_id: string
          updated_at?: string
          waist_inches: number
        }
        Update: {
          arms_inches?: number
          chest_inches?: number
          created_at?: string
          hips_inches?: number
          id?: string
          measured_at?: string
          metadata?: Json
          migration_source?: string | null
          migration_source_id?: string | null
          migration_source_item_key?: string | null
          patient_id?: string
          tenant_id?: string
          updated_at?: string
          waist_inches?: number
        }
        Relationships: [
          {
            foreignKeyName: "patient_body_measurement_entries_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "patient_body_measurement_entries_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      patients: {
        Row: {
          access_status: Database["public"]["Enums"]["patient_access_status"]
          allergies: Json | null
          auth_user_id: string | null
          billing_address_line1: string | null
          billing_address_line2: string | null
          billing_city: string | null
          billing_company: string | null
          billing_country: string | null
          billing_first_name: string | null
          billing_last_name: string | null
          billing_postal_code: string | null
          billing_state: string | null
          conditions: Json | null
          country: string | null
          created_at: string
          date_of_birth: string | null
          email: string
          external_id: string | null
          first_name: string
          id: string
          last_name: string
          medications: Json | null
          metadata: Json | null
          phone: string | null
          shipping_address_line1: string | null
          shipping_address_line2: string | null
          shipping_city: string | null
          shipping_company: string | null
          shipping_country: string | null
          shipping_first_name: string | null
          shipping_instructions: string | null
          shipping_last_name: string | null
          shipping_postal_code: string | null
          shipping_state: string | null
          starting_weight: number | null
          subscribed_to_email_marketing: boolean
          subscribed_to_sms_marketing: boolean
          target_weight: number | null
          tenant_id: string
          updated_at: string
          vitals: Json | null
        }
        Insert: {
          access_status?: Database["public"]["Enums"]["patient_access_status"]
          allergies?: Json | null
          auth_user_id?: string | null
          billing_address_line1?: string | null
          billing_address_line2?: string | null
          billing_city?: string | null
          billing_company?: string | null
          billing_country?: string | null
          billing_first_name?: string | null
          billing_last_name?: string | null
          billing_postal_code?: string | null
          billing_state?: string | null
          conditions?: Json | null
          country?: string | null
          created_at?: string
          date_of_birth?: string | null
          email: string
          external_id?: string | null
          first_name: string
          id?: string
          last_name: string
          medications?: Json | null
          metadata?: Json | null
          phone?: string | null
          shipping_address_line1?: string | null
          shipping_address_line2?: string | null
          shipping_city?: string | null
          shipping_company?: string | null
          shipping_country?: string | null
          shipping_first_name?: string | null
          shipping_instructions?: string | null
          shipping_last_name?: string | null
          shipping_postal_code?: string | null
          shipping_state?: string | null
          starting_weight?: number | null
          subscribed_to_email_marketing?: boolean
          subscribed_to_sms_marketing?: boolean
          target_weight?: number | null
          tenant_id: string
          updated_at?: string
          vitals?: Json | null
        }
        Update: {
          access_status?: Database["public"]["Enums"]["patient_access_status"]
          allergies?: Json | null
          auth_user_id?: string | null
          billing_address_line1?: string | null
          billing_address_line2?: string | null
          billing_city?: string | null
          billing_company?: string | null
          billing_country?: string | null
          billing_first_name?: string | null
          billing_last_name?: string | null
          billing_postal_code?: string | null
          billing_state?: string | null
          conditions?: Json | null
          country?: string | null
          created_at?: string
          date_of_birth?: string | null
          email?: string
          external_id?: string | null
          first_name?: string
          id?: string
          last_name?: string
          medications?: Json | null
          metadata?: Json | null
          phone?: string | null
          shipping_address_line1?: string | null
          shipping_address_line2?: string | null
          shipping_city?: string | null
          shipping_company?: string | null
          shipping_country?: string | null
          shipping_first_name?: string | null
          shipping_instructions?: string | null
          shipping_last_name?: string | null
          shipping_postal_code?: string | null
          shipping_state?: string | null
          starting_weight?: number | null
          subscribed_to_email_marketing?: boolean
          subscribed_to_sms_marketing?: boolean
          target_weight?: number | null
          tenant_id?: string
          updated_at?: string
          vitals?: Json | null
        }
        Relationships: [
          {
            foreignKeyName: "patients_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      payment_providers: {
        Row: {
          created_at: string
          description: string | null
          id: string
          is_active: boolean
          key: string
          logo_url: string | null
          name: string
          required_settings: Json
          updated_at: string
        }
        Insert: {
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          key: string
          logo_url?: string | null
          name: string
          required_settings?: Json
          updated_at?: string
        }
        Update: {
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          key?: string
          logo_url?: string | null
          name?: string
          required_settings?: Json
          updated_at?: string
        }
        Relationships: []
      }
      platform_integrations: {
        Row: {
          category: string
          created_at: string
          description: string | null
          id: string
          is_active: boolean
          key: string
          logo_url: string | null
          name: string
          required_settings: Json
          updated_at: string
        }
        Insert: {
          category?: string
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          key: string
          logo_url?: string | null
          name: string
          required_settings?: Json
          updated_at?: string
        }
        Update: {
          category?: string
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          key?: string
          logo_url?: string | null
          name?: string
          required_settings?: Json
          updated_at?: string
        }
        Relationships: []
      }
      platform_settings: {
        Row: {
          category: string
          created_at: string
          description: string | null
          id: string
          key: string
          updated_at: string
          value: Json
        }
        Insert: {
          category: string
          created_at?: string
          description?: string | null
          id?: string
          key: string
          updated_at?: string
          value?: Json
        }
        Update: {
          category?: string
          created_at?: string
          description?: string | null
          id?: string
          key?: string
          updated_at?: string
          value?: Json
        }
        Relationships: []
      }
      platform_terms_versions: {
        Row: {
          content: string
          created_at: string
          created_by_admin_user_id: string | null
          id: string
          is_live: boolean
          published_at: string | null
          published_by_admin_user_id: string | null
          tenant_id: string | null
          updated_at: string
          version: number
        }
        Insert: {
          content: string
          created_at?: string
          created_by_admin_user_id?: string | null
          id?: string
          is_live?: boolean
          published_at?: string | null
          published_by_admin_user_id?: string | null
          tenant_id?: string | null
          updated_at?: string
          version: number
        }
        Update: {
          content?: string
          created_at?: string
          created_by_admin_user_id?: string | null
          id?: string
          is_live?: boolean
          published_at?: string | null
          published_by_admin_user_id?: string | null
          tenant_id?: string | null
          updated_at?: string
          version?: number
        }
        Relationships: [
          {
            foreignKeyName: "platform_terms_versions_created_by_admin_user_id_fkey"
            columns: ["created_by_admin_user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "platform_terms_versions_published_by_admin_user_id_fkey"
            columns: ["published_by_admin_user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "platform_terms_versions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      privacy_policy_versions: {
        Row: {
          content: string
          created_at: string
          created_by_admin_user_id: string | null
          id: string
          is_live: boolean
          published_at: string | null
          published_by_admin_user_id: string | null
          tenant_id: string
          updated_at: string
          version: number
        }
        Insert: {
          content: string
          created_at?: string
          created_by_admin_user_id?: string | null
          id?: string
          is_live?: boolean
          published_at?: string | null
          published_by_admin_user_id?: string | null
          tenant_id: string
          updated_at?: string
          version: number
        }
        Update: {
          content?: string
          created_at?: string
          created_by_admin_user_id?: string | null
          id?: string
          is_live?: boolean
          published_at?: string | null
          published_by_admin_user_id?: string | null
          tenant_id?: string
          updated_at?: string
          version?: number
        }
        Relationships: [
          {
            foreignKeyName: "privacy_policy_versions_created_by_admin_user_id_fkey"
            columns: ["created_by_admin_user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "privacy_policy_versions_published_by_admin_user_id_fkey"
            columns: ["published_by_admin_user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "privacy_policy_versions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      product_categories: {
        Row: {
          created_at: string
          description: string | null
          display_order: number
          id: string
          is_active: boolean
          key: string
          name: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          description?: string | null
          display_order?: number
          id?: string
          is_active?: boolean
          key: string
          name: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          description?: string | null
          display_order?: number
          id?: string
          is_active?: boolean
          key?: string
          name?: string
          updated_at?: string
        }
        Relationships: []
      }
      product_category_assignments: {
        Row: {
          category_id: string
          created_at: string
          id: string
          product_id: string
        }
        Insert: {
          category_id: string
          created_at?: string
          id?: string
          product_id: string
        }
        Update: {
          category_id?: string
          created_at?: string
          id?: string
          product_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_category_assignments_category_id_fkey"
            columns: ["category_id"]
            isOneToOne: false
            referencedRelation: "product_categories"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_category_assignments_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
        ]
      }
      product_faqs: {
        Row: {
          answer: string
          created_at: string
          display_order: number
          id: string
          product_id: string
          question: string
          updated_at: string
        }
        Insert: {
          answer: string
          created_at?: string
          display_order?: number
          id?: string
          product_id: string
          question: string
          updated_at?: string
        }
        Update: {
          answer?: string
          created_at?: string
          display_order?: number
          id?: string
          product_id?: string
          question?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_faqs_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
        ]
      }
      product_medications: {
        Row: {
          id: string
          instructions: string | null
          medication_id: string
          product_id: string
          quantity: number
        }
        Insert: {
          id?: string
          instructions?: string | null
          medication_id: string
          product_id: string
          quantity?: number
        }
        Update: {
          id?: string
          instructions?: string | null
          medication_id?: string
          product_id?: string
          quantity?: number
        }
        Relationships: [
          {
            foreignKeyName: "product_medications_medication_id_fkey"
            columns: ["medication_id"]
            isOneToOne: false
            referencedRelation: "medications"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_medications_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
        ]
      }
      product_payment_providers: {
        Row: {
          created_at: string
          id: string
          is_enabled: boolean
          product_id: string
          tenant_payment_provider_id: string
        }
        Insert: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          product_id: string
          tenant_payment_provider_id: string
        }
        Update: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          product_id?: string
          tenant_payment_provider_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_payment_providers_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_payment_providers_tenant_payment_provider_id_fkey"
            columns: ["tenant_payment_provider_id"]
            isOneToOne: false
            referencedRelation: "tenant_payment_providers"
            referencedColumns: ["id"]
          },
        ]
      }
      product_provider_platform_load_balancing_rule_set_allocations: {
        Row: {
          allocation_percentage: number
          created_at: string
          id: string
          product_id: string
          product_provider_platform_id: string
          rule_set_id: string
          updated_at: string
        }
        Insert: {
          allocation_percentage: number
          created_at?: string
          id?: string
          product_id: string
          product_provider_platform_id: string
          rule_set_id: string
          updated_at?: string
        }
        Update: {
          allocation_percentage?: number
          created_at?: string
          id?: string
          product_id?: string
          product_provider_platform_id?: string
          rule_set_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_provider_platform_lo_product_provider_platform_id_fkey1"
            columns: ["product_provider_platform_id", "product_id"]
            isOneToOne: false
            referencedRelation: "product_provider_platforms"
            referencedColumns: ["id", "product_id"]
          },
          {
            foreignKeyName: "product_provider_platform_load_bal_rule_set_id_product_id_fkey1"
            columns: ["rule_set_id", "product_id"]
            isOneToOne: false
            referencedRelation: "product_provider_platform_load_balancing_rule_sets"
            referencedColumns: ["id", "product_id"]
          },
        ]
      }
      product_provider_platform_load_balancing_rule_set_states: {
        Row: {
          created_at: string
          id: string
          product_id: string
          rule_set_id: string
          state_code: string
        }
        Insert: {
          created_at?: string
          id?: string
          product_id: string
          rule_set_id: string
          state_code: string
        }
        Update: {
          created_at?: string
          id?: string
          product_id?: string
          rule_set_id?: string
          state_code?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_provider_platform_load_bala_rule_set_id_product_id_fkey"
            columns: ["rule_set_id", "product_id"]
            isOneToOne: false
            referencedRelation: "product_provider_platform_load_balancing_rule_sets"
            referencedColumns: ["id", "product_id"]
          },
        ]
      }
      product_provider_platform_load_balancing_rule_sets: {
        Row: {
          created_at: string
          id: string
          is_default: boolean
          product_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          is_default?: boolean
          product_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          is_default?: boolean
          product_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_provider_platform_load_balancing_rule_s_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
        ]
      }
      product_provider_platforms: {
        Row: {
          created_at: string
          id: string
          is_enabled: boolean
          jotform_new_order_questionnaire_id: string | null
          jotform_renewall_questionnaire_id: string | null
          offering_id: string | null
          product_id: string
          provider_product_sku: string | null
          provider_product_variation_sku: string | null
          questionnaire_id: string | null
          tenant_integration_id: string
        }
        Insert: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          jotform_new_order_questionnaire_id?: string | null
          jotform_renewall_questionnaire_id?: string | null
          offering_id?: string | null
          product_id: string
          provider_product_sku?: string | null
          provider_product_variation_sku?: string | null
          questionnaire_id?: string | null
          tenant_integration_id: string
        }
        Update: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          jotform_new_order_questionnaire_id?: string | null
          jotform_renewall_questionnaire_id?: string | null
          offering_id?: string | null
          product_id?: string
          provider_product_sku?: string | null
          provider_product_variation_sku?: string | null
          questionnaire_id?: string | null
          tenant_integration_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_provider_platforms_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_provider_platforms_tenant_integration_id_fkey"
            columns: ["tenant_integration_id"]
            isOneToOne: false
            referencedRelation: "tenant_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      product_questionnaire_links: {
        Row: {
          display_order: number
          id: string
          is_required: boolean
          product_id: string
          questionnaire_template_id: string
        }
        Insert: {
          display_order?: number
          id?: string
          is_required?: boolean
          product_id: string
          questionnaire_template_id: string
        }
        Update: {
          display_order?: number
          id?: string
          is_required?: boolean
          product_id?: string
          questionnaire_template_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "product_questionnaire_links_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "product_questionnaire_links_questionnaire_template_id_fkey"
            columns: ["questionnaire_template_id"]
            isOneToOne: false
            referencedRelation: "questionnaire_templates"
            referencedColumns: ["id"]
          },
        ]
      }
      products: {
        Row: {
          compare_at_price_cents: number | null
          created_at: string
          description: string | null
          id: string
          image_url: string | null
          is_enabled: boolean
          metadata: Json | null
          name: string
          payment_type: Database["public"]["Enums"]["payment_type"]
          price_cents: number
          provider_sku: string | null
          renewal_advance_max_weeks: number
          sku: string | null
          subscription_interval:
            | Database["public"]["Enums"]["subscription_interval"]
            | null
          subscription_interval_count: number | null
          subscription_renewal_lead_days: number
          tenant_id: string
          terms_and_conditions_html: string | null
          updated_at: string
        }
        Insert: {
          compare_at_price_cents?: number | null
          created_at?: string
          description?: string | null
          id?: string
          image_url?: string | null
          is_enabled?: boolean
          metadata?: Json | null
          name: string
          payment_type?: Database["public"]["Enums"]["payment_type"]
          price_cents?: number
          provider_sku?: string | null
          renewal_advance_max_weeks?: number
          sku?: string | null
          subscription_interval?:
            | Database["public"]["Enums"]["subscription_interval"]
            | null
          subscription_interval_count?: number | null
          subscription_renewal_lead_days?: number
          tenant_id: string
          terms_and_conditions_html?: string | null
          updated_at?: string
        }
        Update: {
          compare_at_price_cents?: number | null
          created_at?: string
          description?: string | null
          id?: string
          image_url?: string | null
          is_enabled?: boolean
          metadata?: Json | null
          name?: string
          payment_type?: Database["public"]["Enums"]["payment_type"]
          price_cents?: number
          provider_sku?: string | null
          renewal_advance_max_weeks?: number
          sku?: string | null
          subscription_interval?:
            | Database["public"]["Enums"]["subscription_interval"]
            | null
          subscription_interval_count?: number | null
          subscription_renewal_lead_days?: number
          tenant_id?: string
          terms_and_conditions_html?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "products_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      provider_logo_assets: {
        Row: {
          created_at: string
          id: string
          is_default: boolean
          logo_url: string
          platform_integration_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          is_default?: boolean
          logo_url: string
          platform_integration_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          is_default?: boolean
          logo_url?: string
          platform_integration_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "provider_logo_assets_platform_integration_id_fkey"
            columns: ["platform_integration_id"]
            isOneToOne: false
            referencedRelation: "platform_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      provider_platform_selection_logs: {
        Row: {
          applied_state_code: string | null
          created_at: string
          id: string
          metadata: Json
          order_id: string | null
          product_id: string
          product_provider_platform_id: string | null
          random_bucket: number | null
          selection_reason: string
          state_code: string | null
          tenant_id: string
          tenant_integration_id: string
        }
        Insert: {
          applied_state_code?: string | null
          created_at?: string
          id?: string
          metadata?: Json
          order_id?: string | null
          product_id: string
          product_provider_platform_id?: string | null
          random_bucket?: number | null
          selection_reason: string
          state_code?: string | null
          tenant_id: string
          tenant_integration_id: string
        }
        Update: {
          applied_state_code?: string | null
          created_at?: string
          id?: string
          metadata?: Json
          order_id?: string | null
          product_id?: string
          product_provider_platform_id?: string | null
          random_bucket?: number | null
          selection_reason?: string
          state_code?: string | null
          tenant_id?: string
          tenant_integration_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "provider_platform_selection_l_product_provider_platform_id_fkey"
            columns: ["product_provider_platform_id"]
            isOneToOne: false
            referencedRelation: "product_provider_platforms"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "provider_platform_selection_logs_order_id_fkey"
            columns: ["order_id"]
            isOneToOne: false
            referencedRelation: "orders"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "provider_platform_selection_logs_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "provider_platform_selection_logs_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "provider_platform_selection_logs_tenant_integration_id_fkey"
            columns: ["tenant_integration_id"]
            isOneToOne: false
            referencedRelation: "tenant_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      questionnaire_templates: {
        Row: {
          created_at: string
          description: string | null
          id: string
          is_active: boolean
          is_shared: boolean
          name: string
          schema: Json
          tenant_id: string | null
          updated_at: string
          version: number
        }
        Insert: {
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          is_shared?: boolean
          name: string
          schema?: Json
          tenant_id?: string | null
          updated_at?: string
          version?: number
        }
        Update: {
          created_at?: string
          description?: string | null
          id?: string
          is_active?: boolean
          is_shared?: boolean
          name?: string
          schema?: Json
          tenant_id?: string | null
          updated_at?: string
          version?: number
        }
        Relationships: [
          {
            foreignKeyName: "questionnaire_templates_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      shipping_availability_notifications: {
        Row: {
          country: string
          created_at: string
          email: string
          id: string
          product_id: string
          shipping_state: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          country?: string
          created_at?: string
          email: string
          id?: string
          product_id: string
          shipping_state: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          country?: string
          created_at?: string
          email?: string
          id?: string
          product_id?: string
          shipping_state?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "shipping_availability_notifications_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "shipping_availability_notifications_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      subscription_events: {
        Row: {
          changed_by: string | null
          changed_by_email: string | null
          created_at: string
          event_type: string
          id: string
          metadata: Json
          new_cancelled_at: string | null
          new_expires_at: string | null
          new_paused_at: string | null
          new_renewal_at: string | null
          new_status: Database["public"]["Enums"]["subscription_status"] | null
          notes: string | null
          old_cancelled_at: string | null
          old_expires_at: string | null
          old_paused_at: string | null
          old_renewal_at: string | null
          old_status: Database["public"]["Enums"]["subscription_status"] | null
          patient_id: string
          subscription_id: string
          tenant_id: string
        }
        Insert: {
          changed_by?: string | null
          changed_by_email?: string | null
          created_at?: string
          event_type: string
          id?: string
          metadata?: Json
          new_cancelled_at?: string | null
          new_expires_at?: string | null
          new_paused_at?: string | null
          new_renewal_at?: string | null
          new_status?: Database["public"]["Enums"]["subscription_status"] | null
          notes?: string | null
          old_cancelled_at?: string | null
          old_expires_at?: string | null
          old_paused_at?: string | null
          old_renewal_at?: string | null
          old_status?: Database["public"]["Enums"]["subscription_status"] | null
          patient_id: string
          subscription_id: string
          tenant_id: string
        }
        Update: {
          changed_by?: string | null
          changed_by_email?: string | null
          created_at?: string
          event_type?: string
          id?: string
          metadata?: Json
          new_cancelled_at?: string | null
          new_expires_at?: string | null
          new_paused_at?: string | null
          new_renewal_at?: string | null
          new_status?: Database["public"]["Enums"]["subscription_status"] | null
          notes?: string | null
          old_cancelled_at?: string | null
          old_expires_at?: string | null
          old_paused_at?: string | null
          old_renewal_at?: string | null
          old_status?: Database["public"]["Enums"]["subscription_status"] | null
          patient_id?: string
          subscription_id?: string
          tenant_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "subscription_events_changed_by_fkey"
            columns: ["changed_by"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_events_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_events_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_events_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      subscription_payment_provider_links: {
        Row: {
          created_at: string
          id: string
          metadata: Json
          payment_provider_id: string
          provider_checkout_session_id: string | null
          provider_subscription_id: string | null
          subscription_id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          metadata?: Json
          payment_provider_id: string
          provider_checkout_session_id?: string | null
          provider_subscription_id?: string | null
          subscription_id: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          metadata?: Json
          payment_provider_id?: string
          provider_checkout_session_id?: string | null
          provider_subscription_id?: string | null
          subscription_id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "subscription_payment_provider_links_payment_provider_id_fkey"
            columns: ["payment_provider_id"]
            isOneToOne: false
            referencedRelation: "payment_providers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_payment_provider_links_subscription_id_fkey"
            columns: ["subscription_id"]
            isOneToOne: false
            referencedRelation: "subscriptions"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscription_payment_provider_links_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      subscriptions: {
        Row: {
          cancellation_reason: string | null
          cancelled_at: string | null
          created_at: string
          current_period_end_at: string | null
          expires_at: string | null
          id: string
          metadata: Json
          patient_id: string
          paused_at: string | null
          product_id: string | null
          started_at: string | null
          status: Database["public"]["Enums"]["subscription_status"]
          stripe_checkout_session_id: string | null
          stripe_subscription_id: string | null
          tenant_id: string
          updated_at: string
        }
        Insert: {
          cancellation_reason?: string | null
          cancelled_at?: string | null
          created_at?: string
          current_period_end_at?: string | null
          expires_at?: string | null
          id?: string
          metadata?: Json
          patient_id: string
          paused_at?: string | null
          product_id?: string | null
          started_at?: string | null
          status?: Database["public"]["Enums"]["subscription_status"]
          stripe_checkout_session_id?: string | null
          stripe_subscription_id?: string | null
          tenant_id: string
          updated_at?: string
        }
        Update: {
          cancellation_reason?: string | null
          cancelled_at?: string | null
          created_at?: string
          current_period_end_at?: string | null
          expires_at?: string | null
          id?: string
          metadata?: Json
          patient_id?: string
          paused_at?: string | null
          product_id?: string | null
          started_at?: string | null
          status?: Database["public"]["Enums"]["subscription_status"]
          stripe_checkout_session_id?: string | null
          stripe_subscription_id?: string | null
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "subscriptions_patient_id_fkey"
            columns: ["patient_id"]
            isOneToOne: false
            referencedRelation: "patients"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscriptions_product_id_fkey"
            columns: ["product_id"]
            isOneToOne: false
            referencedRelation: "products"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "subscriptions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_activity_definitions: {
        Row: {
          created_at: string
          display_order: number
          id: string
          is_active: boolean
          label: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_activity_definitions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_branding: {
        Row: {
          accent_color: string | null
          aria_logo_url: string | null
          created_at: string
          custom_css: string | null
          favicon_url: string | null
          font_family: string | null
          hipaa_url: string | null
          id: string
          logo_url: string | null
          primary_color: string | null
          privacy_url: string | null
          rise_logo_url: string | null
          secondary_color: string | null
          support_email: string | null
          tenant_id: string
          terms_url: string | null
          updated_at: string
        }
        Insert: {
          accent_color?: string | null
          aria_logo_url?: string | null
          created_at?: string
          custom_css?: string | null
          favicon_url?: string | null
          font_family?: string | null
          hipaa_url?: string | null
          id?: string
          logo_url?: string | null
          primary_color?: string | null
          privacy_url?: string | null
          rise_logo_url?: string | null
          secondary_color?: string | null
          support_email?: string | null
          tenant_id: string
          terms_url?: string | null
          updated_at?: string
        }
        Update: {
          accent_color?: string | null
          aria_logo_url?: string | null
          created_at?: string
          custom_css?: string | null
          favicon_url?: string | null
          font_family?: string | null
          hipaa_url?: string | null
          id?: string
          logo_url?: string | null
          primary_color?: string | null
          privacy_url?: string | null
          rise_logo_url?: string | null
          secondary_color?: string | null
          support_email?: string | null
          tenant_id?: string
          terms_url?: string | null
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_branding_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: true
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_feature_flag_overrides: {
        Row: {
          created_at: string
          enabled: boolean
          exclusive_option_id: string | null
          feature_flag_id: string
          id: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          enabled: boolean
          exclusive_option_id?: string | null
          feature_flag_id: string
          id?: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          enabled?: boolean
          exclusive_option_id?: string | null
          feature_flag_id?: string
          id?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_feature_flag_overrides_exclusive_option_id_fkey"
            columns: ["exclusive_option_id"]
            isOneToOne: false
            referencedRelation: "exclusive_flag_options"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "tenant_feature_flag_overrides_feature_flag_id_fkey"
            columns: ["feature_flag_id"]
            isOneToOne: false
            referencedRelation: "feature_flags"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "tenant_feature_flag_overrides_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_injection_site_definitions: {
        Row: {
          created_at: string
          display_order: number
          id: string
          image_url: string
          is_active: boolean
          label: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          display_order?: number
          id?: string
          image_url: string
          is_active?: boolean
          label: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          display_order?: number
          id?: string
          image_url?: string
          is_active?: boolean
          label?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_injection_site_definitions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_integration_auth_tokens: {
        Row: {
          access_token: string
          created_at: string
          expires_at: string
          refreshed_at: string
          tenant_id: string
          tenant_integration_id: string
          updated_at: string
        }
        Insert: {
          access_token: string
          created_at?: string
          expires_at: string
          refreshed_at?: string
          tenant_id: string
          tenant_integration_id: string
          updated_at?: string
        }
        Update: {
          access_token?: string
          created_at?: string
          expires_at?: string
          refreshed_at?: string
          tenant_id?: string
          tenant_integration_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_integration_auth_tokens_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "tenant_integration_auth_tokens_tenant_integration_id_fkey"
            columns: ["tenant_integration_id"]
            isOneToOne: true
            referencedRelation: "tenant_integrations"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_integrations: {
        Row: {
          created_at: string
          id: string
          integration_key: string
          is_enabled: boolean
          provider_legal_agreement: string | null
          settings: Json
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          integration_key: string
          is_enabled?: boolean
          provider_legal_agreement?: string | null
          settings?: Json
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          integration_key?: string
          is_enabled?: boolean
          provider_legal_agreement?: string | null
          settings?: Json
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_integrations_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_memberships: {
        Row: {
          admin_user_id: string
          created_at: string
          id: string
          is_primary: boolean
          tenant_id: string
        }
        Insert: {
          admin_user_id: string
          created_at?: string
          id?: string
          is_primary?: boolean
          tenant_id: string
        }
        Update: {
          admin_user_id?: string
          created_at?: string
          id?: string
          is_primary?: boolean
          tenant_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_memberships_admin_user_id_fkey"
            columns: ["admin_user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "tenant_memberships_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_module_subscriptions: {
        Row: {
          config: Json | null
          created_at: string
          id: string
          is_enabled: boolean
          module_key: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          config?: Json | null
          created_at?: string
          id?: string
          is_enabled?: boolean
          module_key: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          config?: Json | null
          created_at?: string
          id?: string
          is_enabled?: boolean
          module_key?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_module_subscriptions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_mood_change_definitions: {
        Row: {
          created_at: string
          display_order: number
          id: string
          is_active: boolean
          label: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_mood_change_definitions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_payment_providers: {
        Row: {
          created_at: string
          id: string
          is_enabled: boolean
          payment_provider_id: string
          settings: Json
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          payment_provider_id: string
          settings?: Json
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          id?: string
          is_enabled?: boolean
          payment_provider_id?: string
          settings?: Json
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_payment_providers_payment_provider_id_fkey"
            columns: ["payment_provider_id"]
            isOneToOne: false
            referencedRelation: "payment_providers"
            referencedColumns: ["id"]
          },
          {
            foreignKeyName: "tenant_payment_providers_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_settings: {
        Row: {
          allowed_countries: string[] | null
          allowed_signup_email_domains: string[]
          allowed_states: string[] | null
          created_at: string
          currency: string
          date_format: string
          id: string
          metadata: Json | null
          signup_domain_restrictions_enabled: boolean
          tenant_id: string
          timezone: string
          updated_at: string
        }
        Insert: {
          allowed_countries?: string[] | null
          allowed_signup_email_domains?: string[]
          allowed_states?: string[] | null
          created_at?: string
          currency?: string
          date_format?: string
          id?: string
          metadata?: Json | null
          signup_domain_restrictions_enabled?: boolean
          tenant_id: string
          timezone?: string
          updated_at?: string
        }
        Update: {
          allowed_countries?: string[] | null
          allowed_signup_email_domains?: string[]
          allowed_states?: string[] | null
          created_at?: string
          currency?: string
          date_format?: string
          id?: string
          metadata?: Json | null
          signup_domain_restrictions_enabled?: boolean
          tenant_id?: string
          timezone?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_settings_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: true
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_support_configs: {
        Row: {
          created_at: string
          faqs: Json
          id: string
          support_hours: string | null
          support_html: string | null
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          faqs?: Json
          id?: string
          support_hours?: string | null
          support_html?: string | null
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          faqs?: Json
          id?: string
          support_hours?: string | null
          support_html?: string | null
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_support_configs_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: true
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenant_symptom_definitions: {
        Row: {
          created_at: string
          display_order: number
          id: string
          is_active: boolean
          label: string
          tenant_id: string
          updated_at: string
        }
        Insert: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label: string
          tenant_id: string
          updated_at?: string
        }
        Update: {
          created_at?: string
          display_order?: number
          id?: string
          is_active?: boolean
          label?: string
          tenant_id?: string
          updated_at?: string
        }
        Relationships: [
          {
            foreignKeyName: "tenant_symptom_definitions_tenant_id_fkey"
            columns: ["tenant_id"]
            isOneToOne: false
            referencedRelation: "tenants"
            referencedColumns: ["id"]
          },
        ]
      }
      tenants: {
        Row: {
          contact_email: string | null
          contact_phone: string | null
          created_at: string
          id: string
          metadata: Json | null
          name: string
          slug: string
          status: Database["public"]["Enums"]["tenant_status"]
          updated_at: string
        }
        Insert: {
          contact_email?: string | null
          contact_phone?: string | null
          created_at?: string
          id?: string
          metadata?: Json | null
          name: string
          slug: string
          status?: Database["public"]["Enums"]["tenant_status"]
          updated_at?: string
        }
        Update: {
          contact_email?: string | null
          contact_phone?: string | null
          created_at?: string
          id?: string
          metadata?: Json | null
          name?: string
          slug?: string
          status?: Database["public"]["Enums"]["tenant_status"]
          updated_at?: string
        }
        Relationships: []
      }
      user_roles: {
        Row: {
          id: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Insert: {
          id?: string
          role: Database["public"]["Enums"]["app_role"]
          user_id: string
        }
        Update: {
          id?: string
          role?: Database["public"]["Enums"]["app_role"]
          user_id?: string
        }
        Relationships: [
          {
            foreignKeyName: "user_roles_user_id_fkey"
            columns: ["user_id"]
            isOneToOne: false
            referencedRelation: "admin_users"
            referencedColumns: ["id"]
          },
        ]
      }
    }
    Views: {
      [_ in never]: never
    }
    Functions: {
      get_admin_user_id: { Args: { _auth_user_id: string }; Returns: string }
      get_all_admin_users: {
        Args: {
          _limit?: number
          _offset?: number
          _search?: string | null
        }
        Returns: {
          admin_user_id: string
          avatar_url: string
          created_at: string
          email: string
          full_name: string | null
          is_active: boolean
          roles: string[]
          tenants: Json
          total_count: number
        }[]
      }
      get_dashboard_overdue_counts: {
        Args: { p_tenant_id: string }
        Returns: {
          admin_status_label: string
          expiration_timer_hours: number
          is_terminal: boolean
          next_step_owner: string
          overdue_count: number
          previous_day_overdue_count: number
          status_id: string
          status_key: string
        }[]
      }
      get_patient_by_auth_id: {
        Args: { _auth_user_id: string }
        Returns: string
      }
      get_platform_superadmins: {
        Args: never
        Returns: {
          admin_user_id: string
          avatar_url: string
          created_at: string
          email: string
          full_name: string
          is_active: boolean
          tenant_count: number
        }[]
      }
      get_tenant_members: {
        Args: { p_tenant_id: string }
        Returns: {
          admin_user_id: string
          avatar_url: string
          email: string
          full_name: string
          is_active: boolean
          is_primary: boolean
          membership_created_at: string
          membership_id: string
          roles: string[]
          tenant_id: string
        }[]
      }
      get_tenant_dashboard_summary: {
        Args: { p_tenant_id: string }
        Returns: {
          active_patients: number
          enabled_products: number
          pending_orders: number
          total_orders: number
          total_patients: number
          total_products: number
        }[]
      }
      list_tenant_subscriptions: {
        Args: {
          p_limit?: number
          p_offset?: number
          p_search?: string | null
          p_sort?: string
          p_status?: Database["public"]["Enums"]["subscription_status"] | null
          p_tenant_id: string
        }
        Returns: {
          created_at: string
          current_period_end_at: string | null
          id: string
          metadata: Json
          patient_first_name: string
          patient_last_name: string
          status: Database["public"]["Enums"]["subscription_status"]
        }[]
      }
      list_tenant_patients: {
        Args: {
          p_cursor_created_at?: string | null
          p_cursor_id?: string | null
          p_limit?: number
          p_search?: string | null
          p_tenant_id: string
        }
        Returns: {
          access_status: Database["public"]["Enums"]["patient_access_status"]
          created_at: string
          email: string
          first_name: string
          id: string
          last_name: string
          metadata: Json
          phone: string | null
        }[]
      }
      get_user_tenant_ids: {
        Args: { _auth_user_id: string }
        Returns: string[]
      }
      export_tenant_orders_page: {
        Args: {
          p_created_from?: string | null
          p_created_to?: string | null
          p_cursor_created_at?: string | null
          p_cursor_id?: string | null
          p_limit?: number
          p_product_id?: string | null
          p_provider_platform?: string | null
          p_search?: string | null
          p_shipping_state?: string | null
          p_status_id?: string | null
          p_tenant_id: string
        }
        Returns: {
          created_at: string
          id: string
          order_number: string
          order_status_label: string | null
          patient_email: string
          patient_first_name: string
          patient_last_name: string
          product_name: string | null
          shipping_state: string | null
          subscription_current_period_end_at: string | null
          subscription_id: string | null
          subscription_order_type: string | null
          subscription_status: string | null
          total_cents: number
        }[]
      }
      list_order_status_history: {
        Args: { p_order_id: string }
        Returns: {
          admin_status_label: string
          changed_by: string | null
          changed_by_email: string | null
          created_at: string
          id: string
          notes: string | null
          order_id: string
          patient_status_label: string | null
          status_id: string
          status_key: string
        }[]
      }
      list_tenant_orders: {
        Args: {
          p_created_from?: string | null
          p_created_to?: string | null
          p_cursor_created_at?: string | null
          p_cursor_id?: string | null
          p_include_metadata?: boolean
          p_limit?: number
          p_product_id?: string | null
          p_provider_platform?: string | null
          p_search?: string | null
          p_shipping_state?: string | null
          p_status_id?: string | null
          p_tenant_id: string
        }
        Returns: {
          created_at: string
          discount_cents: number
          id: string
          metadata: Json | null
          order_number: string
          order_status_id: string | null
          order_status_is_terminal: boolean | null
          order_status_key: string | null
          order_status_label: string | null
          order_status_next_step_owner: string | null
          patient_email: string
          patient_first_name: string
          patient_last_name: string
          product_name: string | null
          provider_platform_integration_key: string
          renewal_at: string | null
          shipping_state: string | null
          status_id: string | null
          subscription_current_period_end_at: string | null
          subscription_id: string | null
          subscription_order_type: string | null
          subscription_status: string | null
          total_cents: number
        }[]
      }
      has_role: {
        Args: {
          _role: Database["public"]["Enums"]["app_role"]
          _user_id: string
        }
        Returns: boolean
      }
      is_patient: { Args: { _auth_user_id: string }; Returns: boolean }
      is_platform_superadmin: {
        Args: { _auth_user_id: string }
        Returns: boolean
      }
      is_customer_support: {
        Args: { _auth_user_id: string }
        Returns: boolean
      }
      has_customer_support_tenant_access: {
        Args: { _auth_user_id: string; _tenant_id: string }
        Returns: boolean
      }
      is_tenant_admin: {
        Args: { _auth_user_id: string; _tenant_id: string }
        Returns: boolean
      }
    }
    Enums: {
      app_role: "platform_superadmin" | "tenant_admin" | "customer_support"
      flag_type: "boolean" | "exclusive_option"
      next_step_owner:
        | "system"
        | "patient"
        | "provider"
        | "pharmacy"
        | "carrier"
        | "ops"
        | "payment_provider"
      patient_access_status: "active" | "suspended" | "deactivated"
      payment_type: "one_time" | "subscription"
      readiness_status: "not_started" | "in_progress" | "ready"
      subscription_interval: "day" | "week" | "month" | "year"
      subscription_status:
        | "active"
        | "paused"
        | "cancelled"
        | "pending_validation"
        | "pending_cancelation"
        | "pending_cancellation"
      tenant_status: "active" | "inactive" | "suspended" | "pending"
    }
    CompositeTypes: {
      [_ in never]: never
    }
  }
}

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">]

export type Tables<
  DefaultSchemaTableNameOrOptions extends
    | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
        DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
      DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
      Row: infer R
    }
    ? R
    : never
  : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
        DefaultSchema["Views"])
    ? (DefaultSchema["Tables"] &
        DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
        Row: infer R
      }
      ? R
      : never
    : never

export type TablesInsert<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Insert: infer I
    }
    ? I
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Insert: infer I
      }
      ? I
      : never
    : never

export type TablesUpdate<
  DefaultSchemaTableNameOrOptions extends
    | keyof DefaultSchema["Tables"]
    | { schema: keyof DatabaseWithoutInternals },
  TableName extends DefaultSchemaTableNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
    : never = never,
> = DefaultSchemaTableNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
      Update: infer U
    }
    ? U
    : never
  : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
    ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
        Update: infer U
      }
      ? U
      : never
    : never

export type Enums<
  DefaultSchemaEnumNameOrOptions extends
    | keyof DefaultSchema["Enums"]
    | { schema: keyof DatabaseWithoutInternals },
  EnumName extends DefaultSchemaEnumNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
    : never = never,
> = DefaultSchemaEnumNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
  : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
    ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
    : never

export type CompositeTypes<
  PublicCompositeTypeNameOrOptions extends
    | keyof DefaultSchema["CompositeTypes"]
    | { schema: keyof DatabaseWithoutInternals },
  CompositeTypeName extends PublicCompositeTypeNameOrOptions extends {
    schema: keyof DatabaseWithoutInternals
  }
    ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
    : never = never,
> = PublicCompositeTypeNameOrOptions extends {
  schema: keyof DatabaseWithoutInternals
}
  ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
  : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
    ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
    : never

export const Constants = {
  public: {
    Enums: {
      app_role: ["platform_superadmin", "tenant_admin", "customer_support"],
      flag_type: ["boolean", "exclusive_option"],
      next_step_owner: [
        "system",
        "patient",
        "provider",
        "pharmacy",
        "carrier",
        "ops",
        "payment_provider",
      ],
      patient_access_status: ["active", "suspended", "deactivated"],
      payment_type: ["one_time", "subscription"],
      readiness_status: ["not_started", "in_progress", "ready"],
      subscription_interval: ["day", "week", "month", "year"],
      subscription_status: [
        "active",
        "paused",
        "cancelled",
        "pending_validation",
        "pending_cancelation",
      ],
      tenant_status: ["active", "inactive", "suspended", "pending"],
    },
  },
} as const
