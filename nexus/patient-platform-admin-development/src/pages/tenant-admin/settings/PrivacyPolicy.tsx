import { LegalDocumentVersions } from './LegalDocumentVersions';

export default function TenantPrivacyPolicy() {
  return (
    <LegalDocumentVersions
      tableName="privacy_policy_versions"
      queryKey="tenant-privacy-policy-versions"
      auditEntityType="tenant_privacy_policy_version"
      title="Privacy Policy"
      description="Create tenant-specific privacy policy versions and control which version patient apps show as live."
      unavailableMessage="Select a tenant to manage privacy policy."
      contentLabel="Privacy policy"
      contentPlaceholder="Enter the tenant privacy policy text..."
      editPlaceholder="Edit the draft privacy policy text..."
      emptyMessage="No privacy policy versions created for this tenant yet."
      requiredMessage="Privacy policy content is required."
      publishedEditMessage="Published privacy policy versions cannot be edited."
      publishedDeleteMessage="Published privacy policy versions cannot be deleted."
      createErrorLogMessage="Failed to create tenant privacy policy version:"
      updateErrorLogMessage="Failed to update tenant privacy policy draft:"
      deleteErrorLogMessage="Failed to delete tenant privacy policy draft:"
      publishErrorLogMessage="Failed to publish tenant privacy policy version:"
    />
  );
}
