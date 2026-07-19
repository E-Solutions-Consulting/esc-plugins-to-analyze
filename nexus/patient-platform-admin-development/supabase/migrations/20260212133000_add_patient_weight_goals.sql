-- Add starting and target weight fields to patients
ALTER TABLE public.patients
  ADD COLUMN starting_weight NUMERIC,
  ADD COLUMN target_weight NUMERIC;
