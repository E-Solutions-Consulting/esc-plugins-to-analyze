import { create } from 'zustand';
import { supabase } from '@/integrations/supabase/client';
import type { Database } from '@/integrations/supabase/types';

const TENANT_STORAGE_KEY = 'allia_selected_tenant';
const DEFAULT_TIMEOUT_MS = 4000;
const DEFAULT_RETRIES = 2;
const ADMIN_ACCESS_REQUIRED_MESSAGE = 'This account does not have access to the admin portal.';
type HydrationMode = 'blocking' | 'background';
type TenantMembershipWithTenant = Database['public']['Tables']['tenant_memberships']['Row'] & {
  tenant: Pick<Database['public']['Tables']['tenants']['Row'], 'name' | 'slug'> | null;
};

function delay(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function withTimeout<T>(promiseLike: PromiseLike<T>, ms: number, label: string): Promise<T> {
  return Promise.race([
    Promise.resolve(promiseLike),
    new Promise<T>((_, reject) =>
      setTimeout(() => reject(new Error(`Timed out after ${ms}ms: ${label}`)), ms),
    ),
  ]);
}

async function retry<T>(
  fn: () => PromiseLike<T>,
  options: { label: string; attempts?: number; timeoutMs?: number; backoffMs?: number },
): Promise<T> {
  const attempts = options.attempts ?? DEFAULT_RETRIES;
  const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  const backoffMs = options.backoffMs ?? 300;
  let lastError: unknown;

  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      return await withTimeout(fn(), timeoutMs, options.label);
    } catch (error) {
      lastError = error;
      if (attempt < attempts) {
        await delay(backoffMs * attempt);
      }
    }
  }

  throw lastError;
}

// Internal flags not exposed through the store
let isHydrating = false;
let hasBootstrappedSession = false;

interface AuthStore extends AuthStoreType {
  // Internal methods for initialization
  _fetchUserData: (authUserId: string, mode?: HydrationMode) => Promise<void>;
  _hydrateFromSession: (nextSession: Session | null, mode?: HydrationMode) => Promise<void>;
  _initialize: () => () => void;
}

export const useAuthStore = create<AuthStore>()((set, get) => ({
  // State
  user: null,
  session: null,
  roles: [],
  tenants: [],
  currentTenantId: null,
  isLoading: true,
  isSessionRefreshing: false,
  isAuthenticated: false,
  profileStatus: 'idle',
  profileError: null,
  isPlatformSuperadmin: false,
  isTenantAdmin: false,
  isCustomerSupport: false,

  // Internal: Fetch user data (admin profile, roles, memberships)
  _fetchUserData: async (authUserId: string, mode: HydrationMode = 'blocking') => {
    const isBlocking = mode === 'blocking';

    try {
      if (isBlocking) {
        set({ profileStatus: 'loading', profileError: null });
      }

      // Fetch admin user
      const { data: adminUser, error: adminError } = await retry(
        () =>
          supabase
            .from('admin_users')
            .select('*')
            .eq('auth_user_id', authUserId)
            .maybeSingle(),
        { label: 'fetch admin user' },
      );

      if (adminError) {
        console.error('Error fetching admin user:', adminError);
        const errorMsg = adminError?.message || 'Unable to load admin profile.';
        if (isBlocking) {
          set({
            user: null,
            roles: [],
            tenants: [],
            currentTenantId: null,
            profileStatus: 'error',
            profileError: errorMsg,
            isPlatformSuperadmin: false,
            isTenantAdmin: false,
            isCustomerSupport: false,
          });
        } else {
          set({ profileError: errorMsg });
        }
        return;
      }

      if (!adminUser) {
        set({
          user: null,
          roles: [],
          tenants: [],
          currentTenantId: null,
          profileStatus: 'error',
          profileError: ADMIN_ACCESS_REQUIRED_MESSAGE,
          isPlatformSuperadmin: false,
          isTenantAdmin: false,
          isCustomerSupport: false,
        });
        return;
      }

      set({ user: adminUser as AdminUser });

      // Fetch roles + memberships in parallel
      let userRoles: { role: string }[] | null = null;
      let rolesError: { message?: string } | null = null;
      let memberships: TenantMembershipWithTenant[] | null = null;
      let membershipsError: { message?: string } | null = null;

      const [rolesResult, membershipsResult] = await Promise.allSettled([
        retry(
          () =>
            supabase
              .from('user_roles')
              .select('role')
              .eq('user_id', (adminUser as AdminUser).id),
          { label: 'fetch roles' },
        ),
        retry(
          () =>
            supabase
              .from('tenant_memberships')
              .select('*, tenant:tenants(name, slug)')
              .eq('admin_user_id', (adminUser as AdminUser).id),
          { label: 'fetch tenant memberships' },
        ),
      ]);

      if (rolesResult.status === 'fulfilled') {
        userRoles = rolesResult.value.data;
        rolesError = rolesResult.value.error;
      } else {
        rolesError = { message: rolesResult.reason?.message || String(rolesResult.reason) };
      }

      if (membershipsResult.status === 'fulfilled') {
        memberships = membershipsResult.value.data;
        membershipsError = membershipsResult.value.error;
      } else {
        membershipsError = { message: membershipsResult.reason?.message || String(membershipsResult.reason) };
      }

      const newRoles = !rolesError && userRoles ? userRoles.map((r) => r.role as AppRole) : get().roles;

      let newTenants = get().tenants;
      let newCurrentTenantId = get().currentTenantId;

      if (!membershipsError && memberships) {
        const enrichedMemberships = memberships.map((m) => ({
          id: m.id,
          admin_user_id: m.admin_user_id,
          tenant_id: m.tenant_id,
          is_primary: m.is_primary,
          created_at: m.created_at,
          tenant_name: m.tenant?.name,
          tenant_slug: m.tenant?.slug,
        })) as TenantMembership[];

        newTenants = enrichedMemberships;

        // Try to restore saved tenant from localStorage, fall back to primary or first
        const savedTenantId = localStorage.getItem(TENANT_STORAGE_KEY);
        const savedMembership = savedTenantId
          ? enrichedMemberships.find((m) => m.tenant_id === savedTenantId)
          : null;

        if (savedMembership) {
          newCurrentTenantId = savedMembership.tenant_id;
        } else {
          const primaryMembership = enrichedMemberships.find((m) => m.is_primary);
          if (primaryMembership) {
            newCurrentTenantId = primaryMembership.tenant_id;
          } else if (enrichedMemberships.length > 0) {
            newCurrentTenantId = enrichedMemberships[0].tenant_id;
          }
        }
      }

      if (rolesError || membershipsError) {
        console.error('Error fetching roles or memberships:', { rolesError, membershipsError });
        const errorMsg = 'Unable to load roles or tenant memberships. Please retry.';
        if (isBlocking) {
          set({
            roles: newRoles,
            tenants: newTenants,
            currentTenantId: newCurrentTenantId,
            profileStatus: 'error',
            profileError: errorMsg,
            isPlatformSuperadmin: newRoles.includes('platform_superadmin'),
            isTenantAdmin: newRoles.includes('tenant_admin'),
            isCustomerSupport: newRoles.includes('customer_support'),
          });
        } else {
          set({ profileError: errorMsg });
        }
        return;
      }

      set({
        roles: newRoles,
        tenants: newTenants,
        currentTenantId: newCurrentTenantId,
        profileStatus: 'ready',
        profileError: null,
        isPlatformSuperadmin: newRoles.includes('platform_superadmin'),
        isTenantAdmin: newRoles.includes('tenant_admin'),
        isCustomerSupport: newRoles.includes('customer_support'),
      });
    } catch (error) {
      console.error('Error fetching user data:', error);
      const errorMsg = error instanceof Error ? error.message : 'Unable to load user data.';
      if (isBlocking) {
        set({ profileStatus: 'error', profileError: errorMsg });
      } else {
        set({ profileError: errorMsg });
      }
    }
  },

  // Internal: Hydrate from a session object
  _hydrateFromSession: async (nextSession: Session | null, mode: HydrationMode = 'blocking') => {
    if (isHydrating) return;
    const isBackground = mode === 'background';
    isHydrating = true;
    try {
      if (isBackground) {
        set({ isSessionRefreshing: true });
      }
      set({ session: nextSession, isAuthenticated: !!nextSession?.user });
      if (nextSession?.user?.id) {
        await get()._fetchUserData(nextSession.user.id, mode);
        return;
      }

      set({
        user: null,
        roles: [],
        tenants: [],
        currentTenantId: null,
        profileStatus: 'idle',
        profileError: null,
        isPlatformSuperadmin: false,
        isTenantAdmin: false,
        isCustomerSupport: false,
      });
    } catch (error) {
      console.error('Error hydrating auth session:', error);
    } finally {
      if (isBackground) {
        set({ isSessionRefreshing: false });
      }
      isHydrating = false;
    }
  },

  // Initialize auth listener - returns cleanup function
  _initialize: () => {
    let isMounted = true;

    const hydrateBlocking = async (nextSession: Session | null) => {
      if (hasBootstrappedSession) return;
      hasBootstrappedSession = true;
      set({ isLoading: true });
      try {
        await get()._hydrateFromSession(nextSession, 'blocking');
      } finally {
        if (isMounted) set({ isLoading: false });
      }
    };

    const hydrateBackground = async (nextSession: Session | null) => {
      await get()._hydrateFromSession(nextSession, 'background');
    };

    // Set up auth state change listener
    const {
      data: { subscription },
    } = supabase.auth.onAuthStateChange((event, nextSession) => {
      if (!isMounted) return;

      if (event === 'SIGNED_OUT') {
        set({
          session: null,
          user: null,
          roles: [],
          tenants: [],
          currentTenantId: null,
          isSessionRefreshing: false,
          profileStatus: 'idle',
          profileError: null,
          isLoading: false,
          isAuthenticated: false,
          isPlatformSuperadmin: false,
          isTenantAdmin: false,
          isCustomerSupport: false,
        });
        return;
      }

      if (event === 'INITIAL_SESSION') {
        void hydrateBlocking(nextSession);
        return;
      }

      if (event === 'SIGNED_IN' || event === 'USER_UPDATED') {
        set({ session: nextSession, isAuthenticated: !!nextSession?.user });

        if (!hasBootstrappedSession) {
          void hydrateBlocking(nextSession);
          return;
        }

        void hydrateBackground(nextSession);
        return;
      }

      // For other events (e.g. TOKEN_REFRESHED), keep session in sync.
      set({ session: nextSession, isAuthenticated: !!nextSession?.user });
    });

    // Fallback initial hydration path in case INITIAL_SESSION is delayed.
    (async () => {
      try {
        const {
          data: { session },
        } = await supabase.auth.getSession();
        if (!isMounted) return;
        await hydrateBlocking(session);
      } catch (error) {
        console.error('Error reading auth session:', error);
        hasBootstrappedSession = true;
        if (isMounted) set({ isLoading: false });
      }
    })();

    return () => {
      isMounted = false;
      subscription.unsubscribe();
    };
  },

  // Public actions
  signIn: async (email: string, password: string) => {
    set({ isLoading: true });
    try {
      const { data, error } = await supabase.auth.signInWithPassword({
        email,
        password,
      });

      if (error) {
        throw new Error(error.message);
      }

      set({
        session: data.session ?? null,
        isAuthenticated: !!data.session?.user,
        profileStatus: 'loading',
        profileError: null,
      });
      void get()._hydrateFromSession(data.session ?? null);
    } finally {
      set({ isLoading: false });
    }
  },

  signUp: async (email: string, password: string, fullName: string) => {
    const { error } = await supabase.auth.signUp({
      email,
      password,
      options: {
        data: {
          full_name: fullName,
        },
        emailRedirectTo: window.location.origin,
      },
    });

    if (error) {
      throw new Error(error.message);
    }
  },

  signOut: async () => {
    const { error } = await supabase.auth.signOut();
    if (error) {
      throw new Error(error.message);
    }
    set({
      session: null,
      user: null,
      roles: [],
      tenants: [],
      currentTenantId: null,
      isSessionRefreshing: false,
      isAuthenticated: false,
      isLoading: false,
      profileStatus: 'idle',
      profileError: null,
      isPlatformSuperadmin: false,
      isTenantAdmin: false,
      isCustomerSupport: false,
    });
  },

  refreshProfile: async () => {
    const { session } = get();
    if (session?.user?.id) {
      await get()._fetchUserData(session.user.id, 'blocking');
    }
  },

  switchTenant: (tenantId: string) => {
    const membership = get().tenants.find((t) => t.tenant_id === tenantId);
    if (membership) {
      set({ currentTenantId: tenantId });
      localStorage.setItem(TENANT_STORAGE_KEY, tenantId);
    }
  },
}));

// Public auth hook backed by the Zustand store.
export function useAuth() {
  return useAuthStore();
}
