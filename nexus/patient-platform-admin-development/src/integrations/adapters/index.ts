// =====================================================
// INTEGRATION REGISTRY - Factory for getting providers
// =====================================================

import type {
  PaymentProvider,
  ProviderPlatformAdapter,
  PharmacyCatalogProvider,
  FulfillmentProvider,
  ShippingTrackingProvider,
  MessagingProvider,
  VideoProvider,
  AiModuleProvider,
} from './interfaces';

import {
  MockPaymentProvider,
  MockProviderPlatformAdapter,
  MockPharmacyCatalogProvider,
  MockFulfillmentProvider,
  MockShippingTrackingProvider,
  MockMessagingProvider,
  MockVideoProvider,
  MockAiModuleProvider,
} from './mock-providers';

// =====================================================
// PROVIDER TYPES
// =====================================================

export type PaymentProviderType = 'mock' | 'stripe' | 'square';
export type ProviderPlatformType = 'mock' | 'telegra';
export type PharmacyCatalogType = 'mock' | 'pillpack' | 'capsule';
export type FulfillmentType = 'mock' | 'shipbob' | 'fulfillment_by_amazon';
export type ShippingTrackingType = 'mock' | 'aftership' | 'shippo';
export type MessagingType = 'mock' | 'twilio' | 'sendgrid';
export type VideoType = 'mock' | 'zoom' | 'daily';
export type AiModuleType = 'mock' | 'lovable_ai';

// =====================================================
// INTEGRATION REGISTRY
// =====================================================

class IntegrationRegistry {
  private paymentProvider: PaymentProvider | null = null;
  private providerPlatform: ProviderPlatformAdapter | null = null;
  private pharmacyCatalog: PharmacyCatalogProvider | null = null;
  private fulfillment: FulfillmentProvider | null = null;
  private shippingTracking: ShippingTrackingProvider | null = null;
  private messaging: MessagingProvider | null = null;
  private video: VideoProvider | null = null;
  private aiModule: AiModuleProvider | null = null;

  // =====================================================
  // PAYMENT PROVIDER
  // =====================================================

  getPaymentProvider(type: PaymentProviderType = 'mock'): PaymentProvider {
    if (!this.paymentProvider || this.paymentProvider.name !== type) {
      switch (type) {
        case 'stripe':
          // TODO: Import and instantiate StripePaymentProvider
          console.warn('Stripe provider not implemented, using mock');
          this.paymentProvider = new MockPaymentProvider();
          break;
        case 'square':
          // TODO: Import and instantiate SquarePaymentProvider
          console.warn('Square provider not implemented, using mock');
          this.paymentProvider = new MockPaymentProvider();
          break;
        default:
          this.paymentProvider = new MockPaymentProvider();
      }
    }
    return this.paymentProvider;
  }

  // =====================================================
  // PROVIDER PLATFORM
  // =====================================================

  getProviderPlatform(type: ProviderPlatformType = 'mock'): ProviderPlatformAdapter {
    if (!this.providerPlatform || this.providerPlatform.name !== type) {
      switch (type) {
        case 'telegra':
          // TODO: Import and instantiate TelegraAdapter
          console.warn('Telegra adapter not implemented, using mock');
          this.providerPlatform = new MockProviderPlatformAdapter();
          break;
        default:
          this.providerPlatform = new MockProviderPlatformAdapter();
      }
    }
    return this.providerPlatform;
  }

  // =====================================================
  // PHARMACY CATALOG
  // =====================================================

  getPharmacyCatalog(type: PharmacyCatalogType = 'mock'): PharmacyCatalogProvider {
    if (!this.pharmacyCatalog || this.pharmacyCatalog.name !== type) {
      switch (type) {
        case 'pillpack':
        case 'capsule':
          console.warn(`${type} provider not implemented, using mock`);
          this.pharmacyCatalog = new MockPharmacyCatalogProvider();
          break;
        default:
          this.pharmacyCatalog = new MockPharmacyCatalogProvider();
      }
    }
    return this.pharmacyCatalog;
  }

  // =====================================================
  // FULFILLMENT
  // =====================================================

  getFulfillment(type: FulfillmentType = 'mock'): FulfillmentProvider {
    if (!this.fulfillment || this.fulfillment.name !== type) {
      switch (type) {
        case 'shipbob':
        case 'fulfillment_by_amazon':
          console.warn(`${type} provider not implemented, using mock`);
          this.fulfillment = new MockFulfillmentProvider();
          break;
        default:
          this.fulfillment = new MockFulfillmentProvider();
      }
    }
    return this.fulfillment;
  }

  // =====================================================
  // SHIPPING TRACKING
  // =====================================================

  getShippingTracking(type: ShippingTrackingType = 'mock'): ShippingTrackingProvider {
    if (!this.shippingTracking || this.shippingTracking.name !== type) {
      switch (type) {
        case 'aftership':
        case 'shippo':
          console.warn(`${type} provider not implemented, using mock`);
          this.shippingTracking = new MockShippingTrackingProvider();
          break;
        default:
          this.shippingTracking = new MockShippingTrackingProvider();
      }
    }
    return this.shippingTracking;
  }

  // =====================================================
  // MESSAGING
  // =====================================================

  getMessaging(type: MessagingType = 'mock'): MessagingProvider {
    if (!this.messaging || this.messaging.name !== type) {
      switch (type) {
        case 'twilio':
        case 'sendgrid':
          console.warn(`${type} provider not implemented, using mock`);
          this.messaging = new MockMessagingProvider();
          break;
        default:
          this.messaging = new MockMessagingProvider();
      }
    }
    return this.messaging;
  }

  // =====================================================
  // VIDEO
  // =====================================================

  getVideo(type: VideoType = 'mock'): VideoProvider {
    if (!this.video || this.video.name !== type) {
      switch (type) {
        case 'zoom':
        case 'daily':
          console.warn(`${type} provider not implemented, using mock`);
          this.video = new MockVideoProvider();
          break;
        default:
          this.video = new MockVideoProvider();
      }
    }
    return this.video;
  }

  // =====================================================
  // AI MODULE
  // =====================================================

  getAiModule(type: AiModuleType = 'mock'): AiModuleProvider {
    if (!this.aiModule || this.aiModule.name !== type) {
      switch (type) {
        case 'lovable_ai':
          // TODO: Import and instantiate LovableAiProvider
          console.warn('Lovable AI provider not implemented, using mock');
          this.aiModule = new MockAiModuleProvider();
          break;
        default:
          this.aiModule = new MockAiModuleProvider();
      }
    }
    return this.aiModule;
  }
}

// Singleton instance
export const integrationRegistry = new IntegrationRegistry();

// Re-export interfaces
export * from './interfaces';
