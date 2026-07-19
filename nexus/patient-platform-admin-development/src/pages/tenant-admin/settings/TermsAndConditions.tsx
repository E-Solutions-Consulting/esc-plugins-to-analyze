import { LegalDocumentVersions } from './LegalDocumentVersions';

export default function TenantTermsAndConditions() {
  return (
    <LegalDocumentVersions
      tableName="platform_terms_versions"
      queryKey="tenant-terms-versions"
      auditEntityType="tenant_terms_version"
      title="Terms & Conditions"
      description="Create tenant-specific versions and control which version patient apps show as live."
      unavailableMessage="Select a tenant to manage terms and conditions."
      contentLabel="Terms and conditions"
      contentPlaceholder="Enter the tenant terms and conditions text..."
      editPlaceholder="Edit the draft terms and conditions text..."
      emptyMessage="No terms versions created for this tenant yet."
      requiredMessage="Terms and conditions content is required."
      publishedEditMessage="Published terms versions cannot be edited."
      publishedDeleteMessage="Published terms versions cannot be deleted."
      createErrorLogMessage="Failed to create tenant terms version:"
      updateErrorLogMessage="Failed to update tenant terms draft:"
      deleteErrorLogMessage="Failed to delete tenant terms draft:"
      publishErrorLogMessage="Failed to publish tenant terms version:"
    />
  );
}
