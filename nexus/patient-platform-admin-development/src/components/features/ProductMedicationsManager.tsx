import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQueries, useMutation, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/stores/authStore';
import { useAuditLog } from '@/hooks/useAuditLog';
import { ROUTES } from '@/lib/constants';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { toast } from 'sonner';
import { Plus, Trash2, Loader2, Pill, ImageIcon } from 'lucide-react';
interface ProductMedicationsManagerProps {
  productId: string;
  productName: string;
  readOnly?: boolean;
}

export function ProductMedicationsManager({
  productId,
  productName,
  readOnly = false,
}: ProductMedicationsManagerProps) {
  const { currentTenantId } = useAuth();
  const { logAction } = useAuditLog();
  const queryClient = useQueryClient();
  const [isAddMedicationDialogOpen, setIsAddMedicationDialogOpen] = useState(false);
  
  const [selectedMedicationId, setSelectedMedicationId] = useState<string>('');
  const [quantity, setQuantity] = useState<string>('1');
  const [instructions, setInstructions] = useState<string>('');

  const resetAddForm = () => {
    setSelectedMedicationId('');
    setQuantity('1');
    setInstructions('');
  };

  const [
    { data: linkedMedications = [], isLoading: isLoadingLinked },
    { data: availableMedications = [], isLoading: isLoadingAvailable }
  ] = useQueries({
    queries: [
      // Fetch linked medications for this product
      {
        queryKey: ['product-medications', productId],
        queryFn: async () => {
          const { data, error } = await supabase
            .from('product_medications')
            .select(`
          id,
          product_id,
          medication_id,
          quantity,
          instructions,
          medication:medications(id, title, image_url)
        `)
            .eq('product_id', productId);

          if (error) throw error;
          return data as unknown as ProductMedication[];
        },
        enabled: !!productId,
      },
      // Fetch all available medications for the tenant
      {
        queryKey: ['medications-for-linking', currentTenantId],
        queryFn: async () => {
          if (!currentTenantId) return [];

          const { data, error } = await supabase
            .from('medications')
            .select('id, title, image_url')
            .eq('tenant_id', currentTenantId)
            .order('title', { ascending: true });

          if (error) throw error;
          return data as Medication[];
        },
        enabled: !!currentTenantId,
      },
    ],
  });

  // Filter out already linked medications
  const unlinkedMedications = availableMedications.filter(
    (med) => !linkedMedications.some((linked) => linked.medication_id === med.id)
  );

  const addMutation = useMutation({
    mutationFn: async () => {
      const quantityNum = parseInt(quantity, 10);
      if (!selectedMedicationId || isNaN(quantityNum) || quantityNum < 1) {
        throw new Error('Please select a medication and enter a valid quantity');
      }

      const { data, error } = await supabase
        .from('product_medications')
        .insert([{
          product_id: productId,
          medication_id: selectedMedicationId,
          quantity: quantityNum,
          instructions: instructions.trim() || null,
        }])
        .select(`
          id,
          product_id,
          medication_id,
          quantity,
          instructions,
          medication:medications(id, title, image_url)
        `)
        .single();

      if (error) throw error;
      return data as unknown as ProductMedication;
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['product-medications', productId] });
      logAction({
        action: 'link',
        entityType: 'product_medication',
        entityId: data.id,
        afterData: {
          product_id: productId,
          product_name: productName,
          medication_id: data.medication_id,
          medication_title: data.medication?.title,
          quantity: data.quantity,
          instructions: data.instructions,
        },
      });
      toast.success(`Linked "${data.medication?.title}" to product`);
      resetAddForm();
      setIsAddMedicationDialogOpen(false);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to link medication');
    },
  });

  const removeMutation = useMutation({
    mutationFn: async (linkId: string) => {
      const beforeData = linkedMedications.find((l) => l.id === linkId);
      
      const { error } = await supabase
        .from('product_medications')
        .delete()
        .eq('id', linkId);

      if (error) throw error;
      return { linkId, beforeData };
    },
    onSuccess: ({ linkId, beforeData }) => {
      queryClient.invalidateQueries({ queryKey: ['product-medications', productId] });
      logAction({
        action: 'unlink',
        entityType: 'product_medication',
        entityId: linkId,
        beforeData: beforeData ? {
          product_id: productId,
          product_name: productName,
          medication_id: beforeData.medication_id,
          medication_title: beforeData.medication?.title,
          quantity: beforeData.quantity,
          instructions: beforeData.instructions,
        } : undefined,
      });
      toast.success('Medication unlinked from product');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to unlink medication');
    },
  });

  const handleAdd = () => {
    addMutation.mutate();
  };

  const handleAddMedicationDialogChange = (open: boolean) => {
    setIsAddMedicationDialogOpen(open);
    if (!open) {
      resetAddForm();
    }
  };

  if (isLoadingLinked || isLoadingAvailable) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Linked Medications List */}
      <div className="space-y-3">
        <div className="flex items-center justify-between gap-3">
          <Label className="text-sm font-medium">Linked Medications</Label>
          {!readOnly && unlinkedMedications.length > 0 && (
            <Button
              variant="outline"
              size="sm"
              onClick={() => setIsAddMedicationDialogOpen(true)}
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Medications
            </Button>
          )}
        </div>
        {linkedMedications.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-8 text-center border border-dashed rounded-lg bg-muted/30">
            <Pill className="h-10 w-10 text-muted-foreground mb-2" />
            <p className="text-sm text-muted-foreground">No medications linked to this product</p>
            {!readOnly && (
            <p className="text-xs text-muted-foreground mt-1">
              Use Add Medications to include them in this product
            </p>
            )}
          </div>
        ) : (
          <div className="space-y-2">
            {linkedMedications.map((link) => (
              <div
                key={link.id}
                className="flex items-center gap-3 p-3 border rounded-lg bg-card"
              >
                <div className="h-10 w-10 rounded bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                  {link.medication?.image_url ? (
                    <img
                      src={link.medication.image_url}
                      alt={link.medication.title}
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <ImageIcon className="h-5 w-5 text-muted-foreground" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  {link.medication ? (
                    <Link
                      to={ROUTES.TENANT_ADMIN.CATALOG.MEDICATION_DETAIL.replace(
                        ':id',
                        link.medication.id
                      )}
                      className="block truncate font-medium hover:underline"
                    >
                      {link.medication.title}
                    </Link>
                  ) : (
                    <p className="font-medium truncate">Unknown medication</p>
                  )}
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <span>Qty: {link.quantity}</span>
                  </div>
                  {link.instructions && (
                    <p className="text-xs text-muted-foreground mt-1 line-clamp-1">{link.instructions}</p>
                  )}
                </div>
                {!readOnly && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => removeMutation.mutate(link.id)}
                  disabled={removeMutation.isPending}
                  className="text-destructive hover:text-destructive hover:bg-destructive/10"
                >
                  {removeMutation.isPending ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                  ) : (
                    <Trash2 className="h-4 w-4" />
                  )}
                </Button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {!readOnly && (
      <Dialog open={isAddMedicationDialogOpen} onOpenChange={handleAddMedicationDialogChange}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Add Medications</DialogTitle>
            <DialogDescription>
              Link available medications to this product.
            </DialogDescription>
          </DialogHeader>

          {unlinkedMedications.length > 0 ? (
            <div className="grid gap-3 py-1">
              <div className="space-y-1.5">
                <Label htmlFor="medication-select" className="text-xs text-muted-foreground">
                  Select Medication
                </Label>
                <Select value={selectedMedicationId} onValueChange={setSelectedMedicationId}>
                  <SelectTrigger id="medication-select">
                    <SelectValue placeholder="Choose a medication..." />
                  </SelectTrigger>
                  <SelectContent>
                    {unlinkedMedications.map((med) => (
                      <SelectItem key={med.id} value={med.id}>
                        <span>{med.title}</span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="quantity-input" className="text-xs text-muted-foreground">
                    Quantity
                  </Label>
                  <Input
                    id="quantity-input"
                    type="number"
                    min="1"
                    value={quantity}
                    onChange={(e) => setQuantity(e.target.value)}
                    placeholder="1"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="instructions-input" className="text-xs text-muted-foreground">
                    Instructions (optional)
                  </Label>
                  <Textarea
                    id="instructions-input"
                    value={instructions}
                    onChange={(e) => setInstructions(e.target.value)}
                    placeholder="e.g., Take twice daily"
                    rows={1}
                    className="min-h-[38px] resize-none"
                  />
                </div>
              </div>
            </div>
          ) : (
            <p className="py-2 text-sm text-muted-foreground">
              All available medications are already linked to this product.
            </p>
          )}

          <DialogFooter>
            <Button variant="outline" onClick={() => handleAddMedicationDialogChange(false)}>
              Cancel
            </Button>
            <Button
              onClick={handleAdd}
              disabled={
                !selectedMedicationId ||
                addMutation.isPending ||
                unlinkedMedications.length === 0
              }
            >
              {addMutation.isPending ? (
                <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              ) : (
                <Plus className="h-4 w-4 mr-2" />
              )}
              Link Medication
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      )}

      {!readOnly && unlinkedMedications.length === 0 && availableMedications.length > 0 && (
        <p className="text-sm text-muted-foreground text-center py-2">
          All available medications have been linked to this product.
        </p>
      )}

      {!readOnly && availableMedications.length === 0 && (
        <p className="text-sm text-muted-foreground text-center py-2">
          No medications available. Create medications in the Medications catalog first.
        </p>
      )}
    </div>
  );
}
