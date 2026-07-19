import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { Badge } from '@/components/ui/badge';
// Badge display component for showing assigned capabilities
interface MedicationCapabilityBadgesProps {
  medicationId: string;
}

export function MedicationCapabilityBadges({ medicationId }: MedicationCapabilityBadgesProps) {
  const { data: capabilities = [] } = useQuery({
    queryKey: ['medication-capability-assignments-with-names', medicationId],
    queryFn: async () => {
      // First get the assignments
      const { data: assignments, error: assignmentsError } = await supabase
        .from('medication_capability_assignments' as 'medication_capabilities')
        .select('capability_id')
        .eq('medication_id' as 'id', medicationId);

      if (assignmentsError) throw assignmentsError;
      
      const capabilityIds = (assignments as unknown as Array<{ capability_id: string }>).map(a => a.capability_id);
      
      if (capabilityIds.length === 0) return [];

      // Then get the capability details
      const { data: capabilitiesData, error: capabilitiesError } = await supabase
        .from('medication_capabilities')
        .select('id, name, key')
        .in('id', capabilityIds);

      if (capabilitiesError) throw capabilitiesError;
      
      return capabilitiesData as Array<{ id: string; name: string; key: string }>;
    },
  });

  if (capabilities.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-1">
      {capabilities.map((cap) => (
        <Badge key={cap.id} variant="outline" className="text-xs">
          {cap.name}
        </Badge>
      ))}
    </div>
  );
}
