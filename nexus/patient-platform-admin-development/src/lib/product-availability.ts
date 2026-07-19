import { richTextToPlainText } from '@/lib/html-content';

interface ProductAvailabilityProduct {
  description?: string | null;
  image_url?: string | null;
  terms_and_conditions_html?: string | null;
}

interface ProductAvailabilityMedication {
  title?: string | null;
  name?: string | null;
  description?: string | null;
  image_url?: string | null;
}

interface ProductAvailabilityChecklist {
  medications: ProductAvailabilityMedication[];
  hasCategories: boolean;
  hasFaqs: boolean;
}

function isBlank(value?: string | null) {
  return !value || value.trim().length === 0;
}

function getMedicationName(medication: ProductAvailabilityMedication, index: number) {
  return medication.title || medication.name || `Medication ${index + 1}`;
}

export function getMissingProductAvailabilityInfo(
  product: ProductAvailabilityProduct,
  checklist: ProductAvailabilityChecklist,
) {
  const missingInfo: string[] = [];

  if (isBlank(product.image_url)) {
    missingInfo.push('Product image');
  }

  if (isBlank(product.description)) {
    missingInfo.push('Product description');
  }

  if (!richTextToPlainText(product.terms_and_conditions_html)) {
    missingInfo.push('Terms and conditions');
  }

  if (!checklist.hasCategories) {
    missingInfo.push('Product category');
  }

  if (!checklist.hasFaqs) {
    missingInfo.push('Product FAQs');
  }

  if (checklist.medications.length === 0) {
    missingInfo.push('Linked medication');
  } else {
    checklist.medications.forEach((medication, index) => {
      const medicationName = getMedicationName(medication, index);

      if (isBlank(medication.image_url)) {
        missingInfo.push(`Medication image for ${medicationName}`);
      }

      if (isBlank(medication.description)) {
        missingInfo.push(`Medication description for ${medicationName}`);
      }
    });
  }

  return missingInfo;
}
