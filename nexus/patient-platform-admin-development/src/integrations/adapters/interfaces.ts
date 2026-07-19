// =====================================================
// INTEGRATION ADAPTERS (Ports & Adapters / Hexagonal Architecture)
// =====================================================

// These interfaces define the contracts for external integrations.
// All vendor-specific code should be behind these adapters.
// Switch providers by swapping adapter implementations.

// =====================================================
// PAYMENT PROVIDER
// =====================================================

export interface PaymentResult {
  success: boolean;
  transactionId?: string;
  error?: string;
}

export interface PaymentProvider {
  name: string;
  createPaymentIntent(amountCents: number, currency: string, metadata?: Record<string, string>): Promise<PaymentResult>;
  capturePayment(transactionId: string): Promise<PaymentResult>;
  refundPayment(transactionId: string, amountCents?: number): Promise<PaymentResult>;
  getPaymentStatus(transactionId: string): Promise<{ status: string }>;
}

// =====================================================
// PROVIDER PLATFORM ADAPTER (e.g., Telegra)
// =====================================================

export interface ProviderPlatformResult {
  success: boolean;
  externalId?: string;
  error?: string;
}

export interface PatientData {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  dateOfBirth?: string;
}

export interface ProviderPlatformAdapter {
  name: string;
  syncPatient(patient: PatientData): Promise<ProviderPlatformResult>;
  createPrescriptionRequest(patientId: string, medicationIds: string[]): Promise<ProviderPlatformResult>;
  getPrescriptionStatus(externalId: string): Promise<{ status: string; approvedAt?: string }>;
  listApprovedMedications(): Promise<{ id: string; name: string; dosage: string }[]>;
}

// =====================================================
// PHARMACY CATALOG PROVIDER
// =====================================================

export interface PharmacyMedication {
  id: string;
  name: string;
  genericName?: string;
  dosage: string;
  form: string;
  ndc?: string;
}

export interface PharmacyCatalogProvider {
  name: string;
  searchMedications(query: string): Promise<PharmacyMedication[]>;
  getMedication(id: string): Promise<PharmacyMedication | null>;
  validateMedication(medicationId: string): Promise<{ valid: boolean; message?: string }>;
  importMedications(medicationIds: string[]): Promise<{ imported: number; failed: number }>;
}

// =====================================================
// FULFILLMENT PROVIDER
// =====================================================

export interface FulfillmentOrder {
  orderId: string;
  items: { sku: string; quantity: number }[];
  shippingAddress: {
    line1: string;
    line2?: string;
    city: string;
    state: string;
    postalCode: string;
    country: string;
  };
}

export interface FulfillmentResult {
  success: boolean;
  externalOrderId?: string;
  error?: string;
}

export interface FulfillmentProvider {
  name: string;
  createOrder(order: FulfillmentOrder): Promise<FulfillmentResult>;
  cancelOrder(externalOrderId: string): Promise<FulfillmentResult>;
  getOrderStatus(externalOrderId: string): Promise<{ status: string; shippedAt?: string }>;
}

// =====================================================
// SHIPPING TRACKING PROVIDER
// =====================================================

export interface TrackingInfo {
  trackingNumber: string;
  carrier: string;
  status: string;
  estimatedDelivery?: string;
  lastUpdate?: string;
  events: { timestamp: string; location: string; description: string }[];
}

export interface ShippingTrackingProvider {
  name: string;
  getTrackingInfo(trackingNumber: string): Promise<TrackingInfo | null>;
  subscribeToUpdates(trackingNumber: string, webhookUrl: string): Promise<{ subscriptionId: string }>;
  unsubscribeFromUpdates(subscriptionId: string): Promise<void>;
}

// =====================================================
// MESSAGING PROVIDER
// =====================================================

export interface MessageResult {
  success: boolean;
  messageId?: string;
  error?: string;
}

export interface MessagingProvider {
  name: string;
  sendSms(to: string, message: string): Promise<MessageResult>;
  sendEmail(to: string, subject: string, body: string, html?: string): Promise<MessageResult>;
  sendPushNotification(userId: string, title: string, body: string): Promise<MessageResult>;
}

// =====================================================
// VIDEO PROVIDER
// =====================================================

export interface VideoSession {
  sessionId: string;
  joinUrl: string;
  hostUrl: string;
  expiresAt: string;
}

export interface VideoProvider {
  name: string;
  createSession(scheduledAt?: string): Promise<VideoSession>;
  getSession(sessionId: string): Promise<VideoSession | null>;
  endSession(sessionId: string): Promise<void>;
}

// =====================================================
// AI MODULE PROVIDER
// =====================================================

export interface AiAnalysisResult {
  success: boolean;
  result?: Record<string, unknown>;
  error?: string;
}

export interface AiModuleProvider {
  name: string;
  analyzePatientData(patientId: string, dataType: string): Promise<AiAnalysisResult>;
  generateInsights(context: Record<string, unknown>): Promise<AiAnalysisResult>;
  summarizeDocument(content: string): Promise<{ summary: string }>;
}
