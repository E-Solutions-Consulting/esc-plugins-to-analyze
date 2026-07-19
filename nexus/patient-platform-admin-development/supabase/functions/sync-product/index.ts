import { serve } from "https://deno.land/std@0.168.0/http/server.ts";
import { createClient } from "npm:@supabase/supabase-js@2.49.2";
import { mapToStripeInterval } from "./helpers.ts";
import { buildCorsHeaders } from "../_shared/cors.ts";

interface ProductData {
  id: string;
  name: string;
  description: string | null;
  price_cents: number;
  payment_type: 'one_time' | 'subscription';
  subscription_interval: 'day' | 'week' | 'month' | 'year' | null;
  subscription_interval_count: number | null;
  sku: string | null;
  image_url: string | null;
  metadata?: Record<string, unknown>;
}

interface SyncRequest {
  action: 'create' | 'update';
  product: ProductData;
  tenant_id: string;
}

interface ProviderSyncResult {
  provider_key: string;
  provider_name: string;
  success: boolean;
  external_product_id?: string;
  external_price_id?: string;
  error?: string;
}

interface SyncResponse {
  success: boolean;
  results: ProviderSyncResult[];
  error?: string;
}

// Search for an existing Stripe product by allia_product_id metadata
async function findExistingStripeProduct(
  secretKey: string,
  alliaProductId: string,
): Promise<{ id: string } | null> {
  const query = `metadata['allia_product_id']:'${alliaProductId}'`;
  const res = await fetch(
    `https://api.stripe.com/v1/products/search?query=${encodeURIComponent(query)}&limit=1`,
    { headers: { Authorization: `Bearer ${secretKey}` } },
  );
  if (!res.ok) return null;
  const body = await res.json();
  const products: Array<{ id: string }> = body?.data ?? [];
  return products.length > 0 ? products[0] : null;
}

// Sync product to Stripe
async function syncToStripe(
  product: ProductData,
  secretKey: string
): Promise<ProviderSyncResult> {
  const result: ProviderSyncResult = {
    provider_key: 'stripe',
    provider_name: 'Stripe',
    success: false,
  };

  try {
    const headers = {
      'Authorization': `Bearer ${secretKey}`,
      'Content-Type': 'application/x-www-form-urlencoded',
    };

    // Check if a Stripe product already exists for this allia product
    const existingProduct = await findExistingStripeProduct(secretKey, product.id);

    let stripeProductId: string;

    if (existingProduct) {
      // Update the existing Stripe product
      const productParams = new URLSearchParams();
      productParams.append('name', product.name);
      if (product.description) {
        productParams.append('description', product.description);
      } else {
        productParams.append('description', '');
      }
      if (product.image_url) productParams.append('images[0]', product.image_url);

      const productResponse = await fetch(`https://api.stripe.com/v1/products/${existingProduct.id}`, {
        method: 'POST',
        headers,
        body: productParams.toString(),
      });

      if (!productResponse.ok) {
        const errorData = await productResponse.json();
        throw new Error(errorData.error?.message || 'Failed to update Stripe product');
      }

      stripeProductId = existingProduct.id;
    } else {
      // Create a new Stripe product
      const productParams = new URLSearchParams();
      productParams.append('name', product.name);
      if (product.description) productParams.append('description', product.description);
      if (product.image_url) productParams.append('images[0]', product.image_url);
      productParams.append('metadata[allia_product_id]', product.id);

      const productResponse = await fetch('https://api.stripe.com/v1/products', {
        method: 'POST',
        headers,
        body: productParams.toString(),
      });

      if (!productResponse.ok) {
        const errorData = await productResponse.json();
        throw new Error(errorData.error?.message || 'Failed to create Stripe product');
      }

      const stripeProduct = await productResponse.json();
      stripeProductId = stripeProduct.id;
    }

    // Create the price (always create a new one for the current state)
    const priceParams = new URLSearchParams();
    priceParams.append('product', stripeProductId);
    priceParams.append('currency', 'usd');
    priceParams.append('unit_amount', product.price_cents.toString());
    priceParams.append('metadata[allia_product_id]', product.id);

    if (product.payment_type === 'subscription' && product.subscription_interval) {
      priceParams.append('recurring[interval]', mapToStripeInterval(product.subscription_interval));
      if (product.subscription_interval_count && product.subscription_interval_count > 1) {
        priceParams.append('recurring[interval_count]', product.subscription_interval_count.toString());
      }
    }

    const priceResponse = await fetch('https://api.stripe.com/v1/prices', {
      method: 'POST',
      headers,
      body: priceParams.toString(),
    });

    if (!priceResponse.ok) {
      const errorData = await priceResponse.json();
      throw new Error(errorData.error?.message || 'Failed to create Stripe price');
    }

    const stripePrice = await priceResponse.json();

    result.success = true;
    result.external_product_id = stripeProductId;
    result.external_price_id = stripePrice.id;

  } catch (error) {
    result.error = error instanceof Error ? error.message : 'Unknown Stripe sync error';
  }

  return result;
}

// Mock provider sync (for testing)
async function syncToMockProvider(
  product: ProductData
): Promise<ProviderSyncResult> {
  // Simulate API delay
  await new Promise(resolve => setTimeout(resolve, 100));

  return {
    provider_key: 'mock',
    provider_name: 'Mock Provider',
    success: true,
    external_product_id: `mock_prod_${product.id.slice(0, 8)}`,
    external_price_id: `mock_price_${Date.now()}`,
  };
}

serve(async (req) => {
  const corsHeaders = buildCorsHeaders(req, {
    allowHeaders: "authorization, x-client-info, apikey, content-type",
  });

  // Handle CORS preflight
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!;
    const supabaseServiceKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
    const supabase = createClient(supabaseUrl, supabaseServiceKey);

    const { action, product, tenant_id } = await req.json() as SyncRequest;

    if (!product || !tenant_id || !action) {
      return new Response(
        JSON.stringify({ success: false, error: 'Missing required fields', results: [] }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const authHeader = req.headers.get('Authorization');
    if (!authHeader) {
      return new Response(
        JSON.stringify({ success: false, error: 'Missing authorization header', results: [] }),
        { status: 401, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const supabaseAnonKey = Deno.env.get('SUPABASE_ANON_KEY')!;
    const supabaseAuth = createClient(supabaseUrl, supabaseAnonKey, {
      global: { headers: { Authorization: authHeader } },
      auth: { autoRefreshToken: false, persistSession: false },
    });

    const { data: { user }, error: userError } = await supabaseAuth.auth.getUser();
    if (userError || !user) {
      return new Response(
        JSON.stringify({ success: false, error: 'Unauthorized', results: [] }),
        { status: 401, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const { data: adminUser, error: adminError } = await supabase
      .from('admin_users')
      .select('id, is_active')
      .eq('auth_user_id', user.id)
      .maybeSingle();

    if (adminError || !adminUser || adminUser.is_active === false) {
      return new Response(
        JSON.stringify({ success: false, error: 'Forbidden', results: [] }),
        { status: 403, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const { data: isSuperadmin } = await supabase.rpc('is_platform_superadmin', {
      _auth_user_id: user.id,
    });

    if (!isSuperadmin) {
      const { data: membership, error: membershipError } = await supabase
        .from('tenant_memberships')
        .select('id')
        .eq('admin_user_id', adminUser.id)
        .eq('tenant_id', tenant_id)
        .maybeSingle();

      if (membershipError || !membership) {
        return new Response(
          JSON.stringify({ success: false, error: 'Forbidden', results: [] }),
          { status: 403, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
        );
      }
    }

    // Fetch tenant's enabled payment providers with their settings
    const { data: tenantProviders, error: providersError } = await supabase
      .from('tenant_payment_providers')
      .select(`
        id,
        is_enabled,
        settings,
        payment_provider_id,
        payment_providers!inner (
          id,
          key,
          name,
          is_active
        )
      `)
      .eq('tenant_id', tenant_id)
      .eq('is_enabled', true);

    if (providersError) {
      return new Response(
        JSON.stringify({ success: false, error: `Failed to fetch providers: ${providersError.message}`, results: [] }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    if (!tenantProviders || tenantProviders.length === 0) {
      // No providers enabled - this is okay, just return success with empty results
      return new Response(
        JSON.stringify({ success: true, results: [], message: 'No payment providers enabled' }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      );
    }

    const results: ProviderSyncResult[] = [];
    let allSuccess = true;

    // Sync to each enabled provider
    for (const tp of tenantProviders) {
      const provider = tp.payment_providers as unknown as { id: string; key: string; name: string; is_active: boolean };
      const settings = tp.settings as Record<string, string>;
      
      let result: ProviderSyncResult;

      switch (provider.key) {
        case 'stripe': {
          const stripeSecretKey = settings?.secret_key;
          if (!stripeSecretKey) {
            result = {
              provider_key: 'stripe',
              provider_name: 'Stripe',
              success: false,
              error: 'Stripe secret key not configured',
            };
          } else {
            result = await syncToStripe(
              product,
              stripeSecretKey
            );
          }
          break;
        }

        case 'mock':
          result = await syncToMockProvider(product);
          break;

        default:
          result = {
            provider_key: provider.key,
            provider_name: provider.name,
            success: false,
            error: `Provider '${provider.key}' sync not implemented`,
          };
      }

      results.push(result);

      if (!result.success) {
        allSuccess = false;
        continue;
      }
    }

    const response: SyncResponse = {
      success: allSuccess,
      results,
    };

    if (!allSuccess) {
      const failedProviders = results.filter(r => !r.success).map(r => r.provider_name);
      response.error = `Sync failed for: ${failedProviders.join(', ')}`;
    }

    return new Response(
      JSON.stringify(response),
      { 
        status: allSuccess ? 200 : 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' } 
      }
    );

  } catch (error) {
    console.error('Sync error:', error);
    return new Response(
      JSON.stringify({ 
        success: false, 
        error: error instanceof Error ? error.message : 'Internal server error',
        results: [] 
      }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    );
  }
});
