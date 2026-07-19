// =====================================================
// MOCK PROVIDERS - Default implementations for development
// =====================================================

import { dateTime } from "@/lib/dayjs";
import type {
  PaymentProvider,
  PaymentResult,
  ProviderPlatformAdapter,
  ProviderPlatformResult,
  PatientData,
  PharmacyCatalogProvider,
  PharmacyMedication,
  FulfillmentProvider,
  FulfillmentOrder,
  FulfillmentResult,
  ShippingTrackingProvider,
  TrackingInfo,
  MessagingProvider,
  MessageResult,
  VideoProvider,
  VideoSession,
  AiModuleProvider,
  AiAnalysisResult,
} from "./interfaces";

// =====================================================
// MOCK PAYMENT PROVIDER
// =====================================================

export class MockPaymentProvider implements PaymentProvider {
  name = "MockPayment";

  async createPaymentIntent(
    amountCents: number,
    currency: string,
  ): Promise<PaymentResult> {
    console.log(
      `[MockPayment] Creating payment intent: ${amountCents} ${currency}`,
    );
    return {
      success: true,
      transactionId: `mock_pi_${Date.now()}`,
    };
  }

  async capturePayment(transactionId: string): Promise<PaymentResult> {
    console.log(`[MockPayment] Capturing payment: ${transactionId}`);
    return { success: true, transactionId };
  }

  async refundPayment(transactionId: string): Promise<PaymentResult> {
    console.log(`[MockPayment] Refunding payment: ${transactionId}`);
    return { success: true, transactionId };
  }

  async getPaymentStatus(transactionId: string): Promise<{ status: string }> {
    console.log(`[MockPayment] Getting status: ${transactionId}`);
    return { status: "completed" };
  }
}

// =====================================================
// MOCK PROVIDER PLATFORM ADAPTER
// =====================================================

export class MockProviderPlatformAdapter implements ProviderPlatformAdapter {
  name = "MockProviderPlatform";

  async syncPatient(patient: PatientData): Promise<ProviderPlatformResult> {
    console.log(`[MockProvider] Syncing patient: ${patient.email}`);
    return { success: true, externalId: `mock_patient_${patient.id}` };
  }

  async createPrescriptionRequest(
    patientId: string,
    medicationIds: string[],
  ): Promise<ProviderPlatformResult> {
    console.log(
      `[MockProvider] Creating prescription for ${patientId}:`,
      medicationIds,
    );
    return { success: true, externalId: `mock_rx_${Date.now()}` };
  }

  async getPrescriptionStatus(
    externalId: string,
  ): Promise<{ status: string; approvedAt?: string }> {
    console.log(`[MockProvider] Getting prescription status: ${externalId}`);
    return { status: "approved", approvedAt: dateTime().toISOString() };
  }

  async listApprovedMedications(): Promise<
    { id: string; name: string; dosage: string }[]
  > {
    return [
      { id: "mock_med_1", name: "Mock Medication A", dosage: "10mg" },
      { id: "mock_med_2", name: "Mock Medication B", dosage: "25mg" },
    ];
  }
}

// =====================================================
// MOCK PHARMACY CATALOG PROVIDER
// =====================================================

export class MockPharmacyCatalogProvider implements PharmacyCatalogProvider {
  name = "MockPharmacy";

  private medications: PharmacyMedication[] = [
    {
      id: "ph_1",
      name: "Lisinopril",
      genericName: "Lisinopril",
      dosage: "10mg",
      form: "tablet",
    },
    {
      id: "ph_2",
      name: "Metformin",
      genericName: "Metformin HCl",
      dosage: "500mg",
      form: "tablet",
    },
    {
      id: "ph_3",
      name: "Atorvastatin",
      genericName: "Atorvastatin Calcium",
      dosage: "20mg",
      form: "tablet",
    },
  ];

  async searchMedications(query: string): Promise<PharmacyMedication[]> {
    const lowerQuery = query.toLowerCase();
    return this.medications.filter(
      (m) =>
        m.name.toLowerCase().includes(lowerQuery) ||
        m.genericName?.toLowerCase().includes(lowerQuery),
    );
  }

  async getMedication(id: string): Promise<PharmacyMedication | null> {
    return this.medications.find((m) => m.id === id) || null;
  }

  async validateMedication(
    medicationId: string,
  ): Promise<{ valid: boolean; message?: string }> {
    const exists = this.medications.some((m) => m.id === medicationId);
    return {
      valid: exists,
      message: exists ? undefined : "Medication not found in catalog",
    };
  }

  async importMedications(
    medicationIds: string[],
  ): Promise<{ imported: number; failed: number }> {
    console.log(`[MockPharmacy] Importing medications:`, medicationIds);
    return { imported: medicationIds.length, failed: 0 };
  }
}

// =====================================================
// MOCK FULFILLMENT PROVIDER
// =====================================================

export class MockFulfillmentProvider implements FulfillmentProvider {
  name = "MockFulfillment";

  async createOrder(order: FulfillmentOrder): Promise<FulfillmentResult> {
    console.log(`[MockFulfillment] Creating order for:`, order.orderId);
    return { success: true, externalOrderId: `mock_ful_${order.orderId}` };
  }

  async cancelOrder(externalOrderId: string): Promise<FulfillmentResult> {
    console.log(`[MockFulfillment] Cancelling order:`, externalOrderId);
    return { success: true, externalOrderId };
  }

  async getOrderStatus(
    externalOrderId: string,
  ): Promise<{ status: string; shippedAt?: string }> {
    console.log(`[MockFulfillment] Getting order status:`, externalOrderId);
    return { status: "processing" };
  }
}

// =====================================================
// MOCK SHIPPING TRACKING PROVIDER
// =====================================================

export class MockShippingTrackingProvider implements ShippingTrackingProvider {
  name = "MockTracking";

  async getTrackingInfo(trackingNumber: string): Promise<TrackingInfo | null> {
    return {
      trackingNumber,
      carrier: "MockCarrier",
      status: "in_transit",
      estimatedDelivery: dateTime().add(3, "day").toISOString(),
      events: [
        {
          timestamp: dateTime().toISOString(),
          location: "Distribution Center",
          description: "Package in transit",
        },
      ],
    };
  }

  async subscribeToUpdates(
    trackingNumber: string,
  ): Promise<{ subscriptionId: string }> {
    console.log(`[MockTracking] Subscribing to updates:`, trackingNumber);
    return { subscriptionId: `mock_sub_${trackingNumber}` };
  }

  async unsubscribeFromUpdates(subscriptionId: string): Promise<void> {
    console.log(`[MockTracking] Unsubscribing:`, subscriptionId);
  }
}

// =====================================================
// MOCK MESSAGING PROVIDER
// =====================================================

export class MockMessagingProvider implements MessagingProvider {
  name = "MockMessaging";

  async sendSms(to: string, message: string): Promise<MessageResult> {
    console.log(`[MockMessaging] SMS to ${to}: ${message}`);
    return { success: true, messageId: `mock_sms_${Date.now()}` };
  }

  async sendEmail(
    to: string,
    subject: string,
    body: string,
  ): Promise<MessageResult> {
    console.log(`[MockMessaging] Email to ${to}: ${subject}`);
    return { success: true, messageId: `mock_email_${Date.now()}` };
  }

  async sendPushNotification(
    userId: string,
    title: string,
  ): Promise<MessageResult> {
    console.log(`[MockMessaging] Push to ${userId}: ${title}`);
    return { success: true, messageId: `mock_push_${Date.now()}` };
  }
}

// =====================================================
// MOCK VIDEO PROVIDER
// =====================================================

export class MockVideoProvider implements VideoProvider {
  name = "MockVideo";

  async createSession(): Promise<VideoSession> {
    const sessionId = `mock_session_${Date.now()}`;
    return {
      sessionId,
      joinUrl: `https://mock-video.example.com/join/${sessionId}`,
      hostUrl: `https://mock-video.example.com/host/${sessionId}`,
      expiresAt: dateTime().add(1, "hour").toISOString(),
    };
  }

  async getSession(sessionId: string): Promise<VideoSession | null> {
    return {
      sessionId,
      joinUrl: `https://mock-video.example.com/join/${sessionId}`,
      hostUrl: `https://mock-video.example.com/host/${sessionId}`,
      expiresAt: dateTime().add(1, "hour").toISOString(),
    };
  }

  async endSession(sessionId: string): Promise<void> {
    console.log(`[MockVideo] Ending session:`, sessionId);
  }
}

// =====================================================
// MOCK AI MODULE PROVIDER
// =====================================================

export class MockAiModuleProvider implements AiModuleProvider {
  name = "MockAI";

  async analyzePatientData(
    patientId: string,
    dataType: string,
  ): Promise<AiAnalysisResult> {
    console.log(`[MockAI] Analyzing ${dataType} for patient ${patientId}`);
    return {
      success: true,
      result: { insights: ["Mock insight 1", "Mock insight 2"] },
    };
  }

  async generateInsights(
    context: Record<string, unknown>,
  ): Promise<AiAnalysisResult> {
    console.log(`[MockAI] Generating insights for context:`, context);
    return { success: true, result: { recommendation: "Mock recommendation" } };
  }

  async summarizeDocument(content: string): Promise<{ summary: string }> {
    return { summary: `Summary of: ${content.substring(0, 100)}...` };
  }
}
