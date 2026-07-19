import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { supabase } from '@/integrations/supabase/client';
import { useAuditLog } from '@/hooks/useAuditLog';
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
import { toast } from 'sonner';
import { HelpCircle, Loader2, Pencil, Plus, Trash2 } from 'lucide-react';

interface ProductFaq {
  id: string;
  product_id: string;
  question: string;
  answer: string;
  display_order: number;
  created_at: string;
  updated_at: string;
}

interface ProductFaqsManagerProps {
  productId: string;
  productName: string;
  readOnly?: boolean;
}

interface FaqFormState {
  question: string;
  answer: string;
  display_order: string;
}

const DEFAULT_FORM_STATE: FaqFormState = {
  question: '',
  answer: '',
  display_order: '1',
};

function sanitizeDisplayOrder(value: string): number {
  const parsed = Number.parseInt(value, 10);
  if (!Number.isFinite(parsed) || parsed < 1) return 1;
  return parsed;
}

export function ProductFaqsManager({
  productId,
  productName,
  readOnly = false,
}: ProductFaqsManagerProps) {
  const queryClient = useQueryClient();
  const { logAction } = useAuditLog();
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [editingFaq, setEditingFaq] = useState<ProductFaq | null>(null);
  const [formState, setFormState] = useState<FaqFormState>(DEFAULT_FORM_STATE);

  const { data: faqs = [], isLoading } = useQuery({
    queryKey: ['product-faqs', productId],
    queryFn: async () => {
      const { data, error } = await supabase
        .from('product_faqs')
        .select('id, product_id, question, answer, display_order, created_at, updated_at')
        .eq('product_id', productId)
        .order('display_order', { ascending: true })
        .order('created_at', { ascending: true });

      if (error) throw error;
      return data as ProductFaq[];
    },
    enabled: !!productId,
  });

  const nextDisplayOrder = useMemo(() => {
    if (faqs.length === 0) return '1';
    const maxOrder = Math.max(...faqs.map((faq) => faq.display_order || 0));
    return String(maxOrder + 1);
  }, [faqs]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      const question = formState.question.trim();
      const answer = formState.answer.trim();
      const displayOrder = sanitizeDisplayOrder(formState.display_order);

      if (!question) throw new Error('Question is required');
      if (!answer) throw new Error('Answer is required');

      if (editingFaq) {
        const beforeData = editingFaq;
        const { data, error } = await supabase
          .from('product_faqs')
          .update({
            question,
            answer,
            display_order: displayOrder,
          })
          .eq('id', editingFaq.id)
          .select('id, product_id, question, answer, display_order, created_at, updated_at')
          .single();

        if (error) throw error;
        return { mode: 'update' as const, faq: data as ProductFaq, beforeData };
      }

      const { data, error } = await supabase
        .from('product_faqs')
        .insert({
          product_id: productId,
          question,
          answer,
          display_order: displayOrder,
        })
        .select('id, product_id, question, answer, display_order, created_at, updated_at')
        .single();

      if (error) throw error;
      return { mode: 'create' as const, faq: data as ProductFaq };
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['product-faqs', productId] });

      if (result.mode === 'update') {
        logAction({
          action: 'update',
          entityType: 'product_faq',
          entityId: result.faq.id,
          beforeData: {
            ...result.beforeData,
            product_name: productName,
          },
          afterData: {
            ...result.faq,
            product_name: productName,
          },
        });
        toast.success('FAQ updated');
      } else {
        logAction({
          action: 'create',
          entityType: 'product_faq',
          entityId: result.faq.id,
          afterData: {
            ...result.faq,
            product_name: productName,
          },
        });
        toast.success('FAQ created');
      }

      setIsDialogOpen(false);
      setEditingFaq(null);
      setFormState(DEFAULT_FORM_STATE);
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to save FAQ');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (faq: ProductFaq) => {
      const { error } = await supabase
        .from('product_faqs')
        .delete()
        .eq('id', faq.id);

      if (error) throw error;
      return faq;
    },
    onSuccess: (faq) => {
      queryClient.invalidateQueries({ queryKey: ['product-faqs', productId] });
      logAction({
        action: 'delete',
        entityType: 'product_faq',
        entityId: faq.id,
        beforeData: {
          ...faq,
          product_name: productName,
        },
      });
      toast.success('FAQ deleted');
    },
    onError: (error) => {
      toast.error(error instanceof Error ? error.message : 'Failed to delete FAQ');
    },
  });

  const handleOpenCreate = () => {
    if (readOnly) return;
    setEditingFaq(null);
    setFormState({
      ...DEFAULT_FORM_STATE,
      display_order: nextDisplayOrder,
    });
    setIsDialogOpen(true);
  };

  const handleOpenEdit = (faq: ProductFaq) => {
    if (readOnly) return;
    setEditingFaq(faq);
    setFormState({
      question: faq.question,
      answer: faq.answer,
      display_order: String(faq.display_order),
    });
    setIsDialogOpen(true);
  };

  const handleDialogOpenChange = (open: boolean) => {
    setIsDialogOpen(open);
    if (!open) {
      setEditingFaq(null);
      setFormState(DEFAULT_FORM_STATE);
    }
  };

  return (
    <div className="border-t pt-4 space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium">FAQs</p>
          <p className="text-xs text-muted-foreground">
            Manage frequently asked questions shown in patient-facing product APIs.
          </p>
        </div>
        {!readOnly && (
          <Button type="button" variant="outline" size="sm" onClick={handleOpenCreate}>
            <Plus className="h-4 w-4 mr-2" />
            Add FAQ
          </Button>
        )}
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
        </div>
      ) : faqs.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-8 text-center border border-dashed rounded-lg bg-muted/30">
          <HelpCircle className="h-10 w-10 text-muted-foreground mb-2" />
          <p className="text-sm text-muted-foreground">No FAQs configured for this product</p>
        </div>
      ) : (
        <div className="space-y-2">
          {faqs.map((faq) => (
            <div key={faq.id} className="rounded-md border bg-muted/10 p-3 space-y-2">
              <div className="flex items-center justify-between gap-3">
                <span className="text-xs text-muted-foreground">Display order: {faq.display_order}</span>
                {!readOnly && (
                  <div className="flex items-center gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => handleOpenEdit(faq)}
                      disabled={saveMutation.isPending}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => deleteMutation.mutate(faq)}
                      disabled={deleteMutation.isPending}
                      className="text-destructive hover:text-destructive hover:bg-destructive/10"
                    >
                      {deleteMutation.isPending ? (
                        <Loader2 className="h-4 w-4 animate-spin" />
                      ) : (
                        <Trash2 className="h-4 w-4" />
                      )}
                    </Button>
                  </div>
                )}
              </div>
              <p className="font-medium text-sm">{faq.question}</p>
              <p className="text-sm text-muted-foreground whitespace-pre-wrap">{faq.answer}</p>
            </div>
          ))}
        </div>
      )}

      {!readOnly && (
      <Dialog open={isDialogOpen} onOpenChange={handleDialogOpenChange}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingFaq ? 'Edit FAQ' : 'Add FAQ'}</DialogTitle>
            <DialogDescription>
              {editingFaq ? 'Update this FAQ entry for the product.' : 'Create a new FAQ entry for this product.'}
            </DialogDescription>
          </DialogHeader>

          <div className="grid gap-4 py-1">
            <div className="space-y-2">
              <Label htmlFor="faq-question">Question</Label>
              <Input
                id="faq-question"
                value={formState.question}
                onChange={(event) => setFormState((current) => ({
                  ...current,
                  question: event.target.value,
                }))}
                maxLength={200}
                placeholder="e.g. How long does shipping take?"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="faq-answer">Answer</Label>
              <Textarea
                id="faq-answer"
                value={formState.answer}
                onChange={(event) => setFormState((current) => ({
                  ...current,
                  answer: event.target.value,
                }))}
                rows={5}
                maxLength={2000}
                placeholder="Provide the patient-facing answer..."
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="faq-display-order">Display Order</Label>
              <Input
                id="faq-display-order"
                type="number"
                min="1"
                value={formState.display_order}
                onChange={(event) => setFormState((current) => ({
                  ...current,
                  display_order: event.target.value,
                }))}
              />
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => handleDialogOpenChange(false)}>
              Cancel
            </Button>
            <Button onClick={() => saveMutation.mutate()} disabled={saveMutation.isPending}>
              {saveMutation.isPending && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      )}
    </div>
  );
}
