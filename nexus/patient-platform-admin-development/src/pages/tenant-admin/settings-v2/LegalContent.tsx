/**
 * Legal — combines the REAL Terms & Conditions and Privacy Policy version
 * managers under one item with two tabs. Reuses the existing
 * LegalDocumentVersions component in `embedded` mode (no nested AdminLayout),
 * with the exact same props the standalone pages pass — so functionality is
 * identical, just re-homed. See docs/SettingsIARedesign.md Part 3.
 */
import { PageHeader } from "@/components/common/PageHeader";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { LegalDocumentVersions } from "@/pages/tenant-admin/settings/LegalDocumentVersions";
import { ProviderLegalAgreements } from "./ProviderLegalAgreements";

export function LegalContent() {
  return (
    <div>
      <PageHeader
        title="Legal"
        description="Terms, privacy, and provider agreement content shown to patients."
      />
      <Tabs defaultValue="terms">
        <TabsList>
          <TabsTrigger value="terms">Terms & Conditions</TabsTrigger>
          <TabsTrigger value="privacy">Privacy Policy</TabsTrigger>
          <TabsTrigger value="provider-agreement">
            Provider Legal Agreement
          </TabsTrigger>
        </TabsList>

        <TabsContent value="terms" className="mt-4">
          <LegalDocumentVersions
            embedded
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
        </TabsContent>

        <TabsContent value="privacy" className="mt-4">
          <LegalDocumentVersions
            embedded
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
        </TabsContent>

        <TabsContent value="provider-agreement" className="mt-4">
          <ProviderLegalAgreements />
        </TabsContent>
      </Tabs>
    </div>
  );
}
