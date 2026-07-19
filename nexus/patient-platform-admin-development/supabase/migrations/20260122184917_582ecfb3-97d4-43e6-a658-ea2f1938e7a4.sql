-- Create platform settings table for global configuration
CREATE TABLE public.platform_settings (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  key TEXT NOT NULL UNIQUE,
  value JSONB NOT NULL DEFAULT '{}',
  description TEXT,
  category TEXT NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  updated_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable RLS
ALTER TABLE public.platform_settings ENABLE ROW LEVEL SECURITY;

-- Only platform superadmins can read platform settings
CREATE POLICY "Platform superadmins can read platform settings"
  ON public.platform_settings
  FOR SELECT
  USING (public.is_platform_superadmin(auth.uid()));

-- Only platform superadmins can update platform settings
CREATE POLICY "Platform superadmins can update platform settings"
  ON public.platform_settings
  FOR UPDATE
  USING (public.is_platform_superadmin(auth.uid()));

-- Trigger for updated_at
CREATE TRIGGER update_platform_settings_updated_at
  BEFORE UPDATE ON public.platform_settings
  FOR EACH ROW
  EXECUTE FUNCTION public.update_updated_at_column();

-- Insert default settings
INSERT INTO public.platform_settings (key, value, description, category) VALUES
  ('notifications_enabled', '{"email": true, "sms": false}'::jsonb, 'Platform-wide notification settings', 'notifications'),
  ('security_mfa_required', '{"enabled": false}'::jsonb, 'Require MFA for all platform users', 'security'),
  ('security_session_timeout', '{"minutes": 60}'::jsonb, 'Session timeout in minutes', 'security'),
  ('security_password_policy', '{"min_length": 8, "require_uppercase": true, "require_number": true}'::jsonb, 'Password policy requirements', 'security'),
  ('email_smtp_configured', '{"configured": true}'::jsonb, 'SMTP configuration status', 'email'),
  ('integrations_count', '{"active": 5}'::jsonb, 'Number of active integrations', 'integrations');