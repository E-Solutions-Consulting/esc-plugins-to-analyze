-- Ensure auth signup trigger does not create admin profiles for patient users.
-- Patient API signups set raw_user_meta_data.user_type = 'patient'.

CREATE OR REPLACE FUNCTION public.handle_new_user()
RETURNS TRIGGER AS $$
BEGIN
  -- Patient accounts should not exist in admin_users.
  IF COALESCE(NEW.raw_user_meta_data->>'user_type', '') = 'patient' THEN
    RETURN NEW;
  END IF;

  INSERT INTO public.admin_users (auth_user_id, email, full_name)
  VALUES (
    NEW.id,
    NEW.email,
    COALESCE(NEW.raw_user_meta_data->>'full_name', NEW.email)
  );

  RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER SET search_path = public;
