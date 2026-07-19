import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Loader2 } from 'lucide-react';

const createAdminSchema = z.object({
  email: z.string().trim().email('Invalid email address'),
  fullName: z.string().trim().min(2, 'Name must be at least 2 characters'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  isPlatformSuperadmin: z.boolean().default(false),
  tenantId: z.string().optional(),
});

type CreateAdminFormData = z.infer<typeof createAdminSchema>;

async function getFunctionErrorMessage(
  error: unknown,
  fallbackMessage: string,
) {
  const functionError = error as {
    context?: {
      json?: () => Promise<unknown>;
    };
    message?: string;
  };

  try {
    const body = await functionError.context?.json?.();
    if (
      body &&
      typeof body === 'object' &&
      'error' in body &&
      typeof body.error === 'string'
    ) {
      return body.error;
    }
  } catch {
    // Fall back to the Supabase error message below.
  }

  return functionError.message || fallbackMessage;
}

function isDuplicateEmailMessage(message: string) {
  const normalizedMessage = message.toLowerCase();

  return (
    normalizedMessage.includes('email') &&
    (normalizedMessage.includes('already registered') ||
      normalizedMessage.includes('already been registered') ||
      normalizedMessage.includes('already belongs') ||
      normalizedMessage.includes('already exists in supabase auth'))
  );
}

interface CreateAdminDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  variant: 'platform' | 'tenant';
  tenantId?: string;
}

export function CreateAdminDialog({
  open,
  onOpenChange,
  variant,
  tenantId,
}: CreateAdminDialogProps) {
  const queryClient = useQueryClient();
  const [isSubmitting, setIsSubmitting] = useState(false);

  const form = useForm<CreateAdminFormData>({
    resolver: zodResolver(createAdminSchema),
    defaultValues: {
      email: '',
      fullName: '',
      password: '',
      isPlatformSuperadmin: false,
      tenantId: tenantId || '',
    },
  });

  const { data: tenants = [] } = useQuery({
    queryKey: ['tenants-for-admin-create'],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('tenants')
        .select('id, name, slug')
        .eq('status', 'active')
        .order('name');

      if (error) throw error;
      return data;
    },
    enabled: variant === 'platform',
  });

  const createAdminMutation = useMutation({
    mutationFn: async (data: CreateAdminFormData) => {
      // Call edge function to create the admin
      const { data: result, error } = await supabase.functions.invoke('create-admin', {
        body: {
          email: data.email,
          fullName: data.fullName,
          password: data.password,
          isPlatformSuperadmin: data.isPlatformSuperadmin,
          tenantId: variant === 'tenant' ? tenantId : data.tenantId,
        },
      });

      if (error) {
        throw new Error(
          await getFunctionErrorMessage(error, 'Failed to create admin'),
        );
      }
      if (result?.error) throw new Error(result.error);

      return result;
    },
    onSuccess: () => {
      toast.success('Admin created successfully');
      queryClient.invalidateQueries({ queryKey: ['platform-superadmins'] });
      queryClient.invalidateQueries({ queryKey: ['all-admin-users'] });
      queryClient.invalidateQueries({ queryKey: ['tenant-members'] });
      form.reset();
      onOpenChange(false);
    },
    onError: (error: Error) => {
      const message = error.message || 'Failed to create admin';

      if (isDuplicateEmailMessage(message)) {
        form.setError('email', {
          type: 'server',
          message,
        });
      }

      toast.error(message);
    },
  });

  const onSubmit = async (data: CreateAdminFormData) => {
    setIsSubmitting(true);
    try {
      await createAdminMutation.mutateAsync(data);
    } finally {
      setIsSubmitting(false);
    }
  };

  const isPlatformSuperadmin = form.watch('isPlatformSuperadmin');

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Create New Admin</DialogTitle>
          <DialogDescription>
            Create a new admin account with a temporary password.
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
            <FormField
              control={form.control}
              name="fullName"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Full Name</FormLabel>
                  <FormControl>
                    <Input placeholder="John Doe" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="email"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Email</FormLabel>
                  <FormControl>
                    <Input type="email" placeholder="admin@example.com" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="password"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>Temporary Password</FormLabel>
                  <FormControl>
                    <Input type="password" placeholder="••••••••" {...field} />
                  </FormControl>
                  <FormDescription>
                    The user should change this after first login.
                  </FormDescription>
                  <FormMessage />
                </FormItem>
              )}
            />

            {variant === 'platform' && (
              <>
                <FormField
                  control={form.control}
                  name="isPlatformSuperadmin"
                  render={({ field }) => (
                    <FormItem className="flex flex-row items-start space-x-3 space-y-0 rounded-md border p-4">
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={field.onChange}
                        />
                      </FormControl>
                      <div className="space-y-1 leading-none">
                        <FormLabel>Platform Superadmin</FormLabel>
                        <FormDescription>
                          Grant full platform administration access
                        </FormDescription>
                      </div>
                    </FormItem>
                  )}
                />

                {!isPlatformSuperadmin && (
                  <FormField
                    control={form.control}
                    name="tenantId"
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>Assign to Tenant</FormLabel>
                        <Select onValueChange={field.onChange} value={field.value}>
                          <FormControl>
                            <SelectTrigger>
                              <SelectValue placeholder="Select a tenant" />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {tenants.map((tenant) => (
                              <SelectItem key={tenant.id} value={tenant.id}>
                                {tenant.name}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormDescription>
                          The tenant this admin will manage
                        </FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                )}
              </>
            )}

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={isSubmitting}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                Create Admin
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
