-- One-time cleanup of duplicate code-less referral_records rows.

DELETE FROM public.referral_records r
USING (
  SELECT id,
    row_number() OVER (
      PARTITION BY tenant_id, friend_email, friendbuy_campaign_id
      ORDER BY created_at ASC, id ASC
    ) AS rn
  FROM public.referral_records
  WHERE referral_code IS NULL
    AND friend_email IS NOT NULL
) dup
WHERE r.id = dup.id
  AND dup.rn > 1;
