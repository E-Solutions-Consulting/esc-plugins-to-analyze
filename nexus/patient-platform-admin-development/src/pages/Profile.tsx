import { FormEvent, useEffect, useMemo, useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { toast } from "sonner";
import { Eye, EyeOff, KeyRound, Loader2, Save, Shield, User } from "lucide-react";

import { PageHeader } from "@/components/common/PageHeader";
import { AdminLayout } from "@/components/layouts/AdminLayout";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import { supabase } from "@/integrations/supabase/client";
import { useAuth } from "@/stores/authStore";

interface ProfilePageProps {
  variant: "tenant" | "platform";
}

function getInitials(name?: string | null, email?: string | null) {
  const initials = name
    ?.split(" ")
    .filter(Boolean)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  return initials || email?.[0]?.toUpperCase() || "U";
}

function formatRole(role: AppRole) {
  return role
    .split("_")
    .map((part) => part[0].toUpperCase() + part.slice(1))
    .join(" ");
}

export function ProfileContent() {
  const { user, roles, tenants, refreshProfile } = useAuth();
  const [fullName, setFullName] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  useEffect(() => {
    setFullName(user?.full_name || "");
  }, [user?.full_name]);

  const initials = useMemo(
    () => getInitials(fullName || user?.full_name, user?.email),
    [fullName, user?.email, user?.full_name],
  );

  const profileMutation = useMutation({
    mutationFn: async () => {
      if (!user?.id) {
        throw new Error("Your profile could not be loaded.");
      }

      const normalizedFullName = fullName.trim();
      const password = newPassword.trim();
      const passwordConfirmation = confirmPassword.trim();

      if (!normalizedFullName) {
        throw new Error("Full name is required.");
      }

      if (password || passwordConfirmation) {
        if (password.length < 8) {
          throw new Error("Password must be at least 8 characters long.");
        }

        if (password !== passwordConfirmation) {
          throw new Error("Passwords do not match.");
        }
      }

      const { error } = await supabase
        .from("admin_users")
        .update({
          full_name: normalizedFullName,
        })
        .eq("id", user.id)
        .eq("auth_user_id", user.auth_user_id || "");

      if (error) {
        throw error;
      }

      if (password) {
        const { error: passwordError } = await supabase.auth.updateUser({
          password,
        });

        if (passwordError) {
          throw passwordError;
        }
      }
    },
    onSuccess: async () => {
      await refreshProfile();
      setNewPassword("");
      setConfirmPassword("");
      setShowNewPassword(false);
      setShowConfirmPassword(false);
      toast.success("Profile updated");
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : "Failed to update profile");
    },
  });

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    profileMutation.mutate();
  };

  if (!user) {
    return (
      <Alert variant="destructive">
        <User className="h-4 w-4" />
        <AlertTitle>Profile unavailable</AlertTitle>
        <AlertDescription>
          We could not load your profile. Refresh the page and try again.
        </AlertDescription>
      </Alert>
    );
  }

  return (
    <div className="max-w-4xl space-y-6">
      <PageHeader
        title="Profile"
        description="View and manage your admin account details."
      />

      <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
        <Card>
          <CardHeader className="items-center text-center">
            <Avatar className="h-24 w-24">
              <AvatarImage src={user.avatar_url || undefined} />
              <AvatarFallback className="text-xl">{initials}</AvatarFallback>
            </Avatar>
            <div className="space-y-1">
              <CardTitle className="text-lg">{fullName || user.email}</CardTitle>
              <CardDescription className="break-all">{user.email}</CardDescription>
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            <Separator />
            <div className="space-y-2">
              <p className="text-sm font-medium">Roles</p>
              <div className="flex flex-wrap gap-2">
                {roles.length > 0 ? (
                  roles.map((role) => (
                    <Badge key={role} variant="secondary">
                      {formatRole(role)}
                    </Badge>
                  ))
                ) : (
                  <p className="text-sm text-muted-foreground">No roles assigned</p>
                )}
              </div>
            </div>
            <div className="space-y-2">
              <p className="text-sm font-medium">Tenant Access</p>
              <p className="text-sm text-muted-foreground">
                {tenants.length === 0
                  ? "No tenant memberships"
                  : `${tenants.length} tenant${tenants.length === 1 ? "" : "s"}`}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-xl">
              <User className="h-5 w-5" />
              Account Information
            </CardTitle>
            <CardDescription>
              Changes here update your admin profile across tenant and platform administration.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="profile-full-name">Full Name</Label>
                  <Input
                    id="profile-full-name"
                    value={fullName}
                    onChange={(event) => setFullName(event.target.value)}
                    autoComplete="name"
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="profile-email">Email</Label>
                  <Input
                    id="profile-email"
                    value={user.email}
                    disabled
                    autoComplete="email"
                  />
                  <p className="text-xs text-muted-foreground">
                    Contact a platform administrator to change your login email.
                  </p>
                </div>
              </div>

              <Separator />

              <div className="space-y-4">
                <div>
                  <h3 className="flex items-center gap-2 text-sm font-medium">
                    <KeyRound className="h-4 w-4" />
                    Password
                  </h3>
                  <p className="text-sm text-muted-foreground">
                    Leave these fields blank to keep your current password.
                  </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-2">
                    <Label htmlFor="profile-new-password">New Password</Label>
                    <div className="relative">
                      <Input
                        id="profile-new-password"
                        type={showNewPassword ? "text" : "password"}
                        value={newPassword}
                        onChange={(event) => setNewPassword(event.target.value)}
                        autoComplete="new-password"
                        className="pr-10"
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute right-0 top-0 h-full px-3 hover:bg-transparent"
                        onClick={() => setShowNewPassword((current) => !current)}
                        aria-label={showNewPassword ? "Hide password" : "Show password"}
                      >
                        {showNewPassword ? (
                          <EyeOff className="h-4 w-4 text-muted-foreground" />
                        ) : (
                          <Eye className="h-4 w-4 text-muted-foreground" />
                        )}
                      </Button>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      Password must be at least 8 characters long.
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="profile-confirm-password">Confirm Password</Label>
                    <div className="relative">
                      <Input
                        id="profile-confirm-password"
                        type={showConfirmPassword ? "text" : "password"}
                        value={confirmPassword}
                        onChange={(event) => setConfirmPassword(event.target.value)}
                        autoComplete="new-password"
                        className="pr-10"
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="absolute right-0 top-0 h-full px-3 hover:bg-transparent"
                        onClick={() => setShowConfirmPassword((current) => !current)}
                        aria-label={showConfirmPassword ? "Hide password" : "Show password"}
                      >
                        {showConfirmPassword ? (
                          <EyeOff className="h-4 w-4 text-muted-foreground" />
                        ) : (
                          <Eye className="h-4 w-4 text-muted-foreground" />
                        )}
                      </Button>
                    </div>
                  </div>
                </div>
              </div>

              <Alert>
                <Shield className="h-4 w-4" />
                <AlertTitle>Account Security</AlertTitle>
                <AlertDescription>
                  Your roles and tenant access are managed by administrators and are shown here for reference.
                </AlertDescription>
              </Alert>

              <div className="flex justify-end">
                <Button type="submit" disabled={profileMutation.isPending}>
                  {profileMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="h-4 w-4" />
                  )}
                  Save Changes
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export default function ProfilePage({ variant }: ProfilePageProps) {
  return (
    <AdminLayout variant={variant}>
      <ProfileContent />
    </AdminLayout>
  );
}
