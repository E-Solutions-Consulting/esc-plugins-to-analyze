import { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
import { toast } from 'sonner';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Loader2, Search, CheckSquare, Square, MapPin } from 'lucide-react';
import { US_STATES } from '@/lib/usStates';

interface AllowedStatesManagerProps {
  tenantId: string;
  allowedStates: string[];
}

export function AllowedStatesManager({ tenantId, allowedStates }: AllowedStatesManagerProps) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();
  const [selectedStates, setSelectedStates] = useState<string[]>(allowedStates || []);
  const [searchQuery, setSearchQuery] = useState('');
  const [hasChanges, setHasChanges] = useState(false);

  useEffect(() => {
    setSelectedStates(allowedStates || []);
    setHasChanges(false);
  }, [allowedStates]);

  const updateMutation = useMutation({
    mutationFn: async (states: string[]) => {
      const { data, error } = await supabase
        .from('tenant_settings')
        .upsert(
          {
            tenant_id: tenantId,
            allowed_states: states,
          },
          { onConflict: 'tenant_id' },
        )
        .select('id, allowed_states')
        .single();

      if (error) throw error;

      return {
        settings: data,
        beforeStates: allowedStates || [],
        afterStates: data?.allowed_states || states,
      };
    },
    onSuccess: ({ settings, beforeStates, afterStates }) => {
      queryClient.invalidateQueries({ queryKey: ['tenant-settings', tenantId] });
      queryClient.invalidateQueries({ queryKey: ['tenant-allowed-states', tenantId] });
      logAction({
        action: 'update',
        entityType: 'tenant_settings',
        entityId: settings.id,
        beforeData: { allowed_states: beforeStates },
        afterData: { allowed_states: afterStates },
      });
      toast.success('Allowed states updated successfully');
      setHasChanges(false);
    },
    onError: (error: Error) => {
      toast.error(error.message || 'Failed to update allowed states');
    },
  });

  const toggleState = (stateCode: string) => {
    setSelectedStates((prev) => {
      const newStates = prev.includes(stateCode)
        ? prev.filter((s) => s !== stateCode)
        : [...prev, stateCode];
      setHasChanges(true);
      return newStates;
    });
  };

  const selectAll = () => {
    setSelectedStates(US_STATES.map((s) => s.code));
    setHasChanges(true);
  };

  const deselectAll = () => {
    setSelectedStates([]);
    setHasChanges(true);
  };

  const handleSave = () => {
    updateMutation.mutate(selectedStates);
  };

  const filteredStates = US_STATES.filter(
    (state) =>
      state.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      state.code.toLowerCase().includes(searchQuery.toLowerCase())
  );

  return (
    <Card>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <MapPin className="h-5 w-5 text-muted-foreground" />
            <div>
              <CardTitle>Allowed States</CardTitle>
              <CardDescription>
                Select which US states you are allowed to sell to
              </CardDescription>
            </div>
          </div>
          <Badge variant="secondary">
            {selectedStates.length} of {US_STATES.length} selected
          </Badge>
        </div>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Search and bulk actions */}
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <Input
              placeholder="Search states..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9"
            />
          </div>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={selectAll}
              className="flex items-center gap-1"
            >
              <CheckSquare className="h-4 w-4" />
              Select All
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={deselectAll}
              className="flex items-center gap-1"
            >
              <Square className="h-4 w-4" />
              Deselect All
            </Button>
          </div>
        </div>

        {/* States grid */}
        <div className="border rounded-lg p-4 max-h-[400px] overflow-y-auto">
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            {filteredStates.map((state) => (
              <div
                key={state.code}
                className="flex items-center space-x-2"
              >
                <Checkbox
                  id={`state-${state.code}`}
                  checked={selectedStates.includes(state.code)}
                  onCheckedChange={() => toggleState(state.code)}
                />
                <Label
                  htmlFor={`state-${state.code}`}
                  className="text-sm cursor-pointer flex items-center gap-1"
                >
                  <span className="font-medium">{state.code}</span>
                  <span className="text-muted-foreground hidden sm:inline">
                    - {state.name}
                  </span>
                </Label>
              </div>
            ))}
          </div>
          {filteredStates.length === 0 && (
            <p className="text-center text-muted-foreground py-4">
              No states match your search
            </p>
          )}
        </div>

        {/* Save button */}
        <div className="flex justify-end">
          <Button
            type="button"
            onClick={handleSave}
            disabled={!hasChanges || updateMutation.isPending}
          >
            {updateMutation.isPending && (
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
            )}
            Save Changes
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
