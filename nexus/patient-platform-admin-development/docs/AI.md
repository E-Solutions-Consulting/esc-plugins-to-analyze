# AI Integration Best Practices

> **Purpose**: Comprehensive guidelines for integrating AI capabilities including ML models, agentic systems, voice AI, knowledge bases, and workflow automation.

---

## Table of Contents

1. [AI Architecture Overview](#ai-architecture-overview)
2. [Machine Learning Integration](#machine-learning-integration)
3. [Agentic AI Systems](#agentic-ai-systems)
4. [Voice AI Integration](#voice-ai-integration)
5. [Knowledge Base Integration](#knowledge-base-integration)
6. [N8N Workflow Automation](#n8n-workflow-automation)
7. [Cloud Provider Integration](#cloud-provider-integration)
8. [Best Practices & Patterns](#best-practices--patterns)
9. [Configuration Standards](#configuration-standards)
10. [Code Examples](#code-examples)

---

## AI Architecture Overview

### AI Stack Components

```
┌────────────────────────────────────────┐
│         Application Layer              │
│     (React Frontend + Backend)         │
└──────────────┬─────────────────────────┘
               │
       ┌───────┴──────────┐
       │                  │
       ▼                  ▼
┌──────────────┐   ┌─────────────────┐
│ AI Services  │   │  N8N Workflows  │
│   Layer      │   │  (Orchestration)│
└──────┬───────┘   └────────┬────────┘
       │                    │
       └────────┬───────────┘
                │
     ┌──────────┴──────────┐
     │                     │
     ▼                     ▼
┌─────────────┐    ┌──────────────┐
│ML Providers │    │Agent Stores  │
│OpenAI, etc. │    │& Knowledge   │
└─────────────┘    └──────────────┘
```

### Core Principles

1. **Provider Agnostic**: Support multiple AI providers
2. **Unified Interface**: Consistent API across providers
3. **Fail-Safe**: Graceful degradation and fallbacks
4. **Cost Aware**: Track usage and implement limits
5. **Secure**: API keys in environment variables
6. **Observable**: Logging and monitoring

---

## Machine Learning Integration

### Supported ML Providers

```typescript
// src/integrations/ai/types.ts
export enum AIProvider {
  OPENAI = 'openai',
  ANTHROPIC = 'anthropic',
  GOOGLE = 'google',
  AZURE = 'azure',
  HUGGINGFACE = 'huggingface',
  REPLICATE = 'replicate'
}

export interface AIProviderConfig {
  provider: AIProvider;
  apiKey: string;
  model: string;
  baseUrl?: string;
  maxTokens?: number;
  temperature?: number;
}
```

### Unified AI Service Interface

```typescript
// src/integrations/ai/base.ts
export interface AIMessage {
  role: 'system' | 'user' | 'assistant';
  content: string;
}

export interface AICompletionOptions {
  model?: string;
  temperature?: number;
  maxTokens?: number;
  stream?: boolean;
}

export interface AICompletionResponse {
  content: string;
  usage: {
    promptTokens: number;
    completionTokens: number;
    totalTokens: number;
  };
  model: string;
  finishReason: string;
}

export abstract class BaseAIProvider {
  abstract createCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): Promise<AICompletionResponse>;

  abstract createEmbedding(text: string): Promise<number[]>;

  abstract streamCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): AsyncIterableIterator<string>;
}
```

### OpenAI Integration

```typescript
// src/integrations/ai/openai.ts
import OpenAI from 'openai';
import { BaseAIProvider, AIMessage, AICompletionOptions, AICompletionResponse } from './base';

export class OpenAIProvider extends BaseAIProvider {
  private client: OpenAI;
  private defaultModel: string;

  constructor(config: { apiKey: string; model?: string }) {
    super();
    this.client = new OpenAI({
      apiKey: config.apiKey || import.meta.env.VITE_OPENAI_API_KEY
    });
    this.defaultModel = config.model || 'gpt-4-turbo-preview';
  }

  async createCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): Promise<AICompletionResponse> {
    const response = await this.client.chat.completions.create({
      model: options?.model || this.defaultModel,
      messages,
      temperature: options?.temperature ?? 0.7,
      max_tokens: options?.maxTokens,
      stream: false
    });

    const choice = response.choices[0];

    return {
      content: choice.message.content || '',
      usage: {
        promptTokens: response.usage?.prompt_tokens || 0,
        completionTokens: response.usage?.completion_tokens || 0,
        totalTokens: response.usage?.total_tokens || 0
      },
      model: response.model,
      finishReason: choice.finish_reason
    };
  }

  async createEmbedding(text: string): Promise<number[]> {
    const response = await this.client.embeddings.create({
      model: 'text-embedding-3-small',
      input: text
    });

    return response.data[0].embedding;
  }

  async *streamCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): AsyncIterableIterator<string> {
    const stream = await this.client.chat.completions.create({
      model: options?.model || this.defaultModel,
      messages,
      temperature: options?.temperature ?? 0.7,
      max_tokens: options?.maxTokens,
      stream: true
    });

    for await (const chunk of stream) {
      const content = chunk.choices[0]?.delta?.content;
      if (content) {
        yield content;
      }
    }
  }
}
```

### Anthropic (Claude) Integration

```typescript
// src/integrations/ai/anthropic.ts
import Anthropic from '@anthropic-ai/sdk';
import { BaseAIProvider, AIMessage, AICompletionOptions, AICompletionResponse } from './base';

export class AnthropicProvider extends BaseAIProvider {
  private client: Anthropic;
  private defaultModel: string;

  constructor(config: { apiKey: string; model?: string }) {
    super();
    this.client = new Anthropic({
      apiKey: config.apiKey || import.meta.env.VITE_ANTHROPIC_API_KEY
    });
    this.defaultModel = config.model || 'claude-3-5-sonnet-20241022';
  }

  async createCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): Promise<AICompletionResponse> {
    // Extract system message
    const systemMessage = messages.find(m => m.role === 'system')?.content;
    const userMessages = messages.filter(m => m.role !== 'system');

    const response = await this.client.messages.create({
      model: options?.model || this.defaultModel,
      system: systemMessage,
      messages: userMessages,
      temperature: options?.temperature ?? 0.7,
      max_tokens: options?.maxTokens || 4096
    });

    const textContent = response.content.find(c => c.type === 'text');

    return {
      content: textContent?.text || '',
      usage: {
        promptTokens: response.usage.input_tokens,
        completionTokens: response.usage.output_tokens,
        totalTokens: response.usage.input_tokens + response.usage.output_tokens
      },
      model: response.model,
      finishReason: response.stop_reason || 'complete'
    };
  }

  async createEmbedding(text: string): Promise<number[]> {
    // Anthropic doesn't provide embedding models
    // Use OpenAI or another provider for embeddings
    throw new Error('Anthropic provider does not support embeddings');
  }

  async *streamCompletion(
    messages: AIMessage[],
    options?: AICompletionOptions
  ): AsyncIterableIterator<string> {
    const systemMessage = messages.find(m => m.role === 'system')?.content;
    const userMessages = messages.filter(m => m.role !== 'system');

    const stream = await this.client.messages.create({
      model: options?.model || this.defaultModel,
      system: systemMessage,
      messages: userMessages,
      temperature: options?.temperature ?? 0.7,
      max_tokens: options?.maxTokens || 4096,
      stream: true
    });

    for await (const event of stream) {
      if (
        event.type === 'content_block_delta' &&
        event.delta.type === 'text_delta'
      ) {
        yield event.delta.text;
      }
    }
  }
}
```

### AI Service Factory

```typescript
// src/integrations/ai/factory.ts
import { AIProvider } from './types';
import { BaseAIProvider } from './base';
import { OpenAIProvider } from './openai';
import { AnthropicProvider } from './anthropic';

export class AIServiceFactory {
  static createProvider(provider: AIProvider, config?: any): BaseAIProvider {
    switch (provider) {
      case AIProvider.OPENAI:
        return new OpenAIProvider(config);

      case AIProvider.ANTHROPIC:
        return new AnthropicProvider(config);

      case AIProvider.GOOGLE:
        // return new GoogleProvider(config);
        throw new Error('Google provider not yet implemented');

      case AIProvider.AZURE:
        // return new AzureProvider(config);
        throw new Error('Azure provider not yet implemented');

      default:
        throw new Error(`Unsupported AI provider: ${provider}`);
    }
  }

  static createDefault(): BaseAIProvider {
    const provider = import.meta.env.VITE_AI_PROVIDER as AIProvider || AIProvider.OPENAI;
    return this.createProvider(provider);
  }
}
```

### Usage Example

```typescript
// src/hooks/useAI.ts
import { useState } from 'react';
import { AIServiceFactory } from '@/integrations/ai/factory';
import { AIMessage } from '@/integrations/ai/base';

export function useAI() {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);

  const generateCompletion = async (
    messages: AIMessage[],
    options?: { temperature?: number; maxTokens?: number }
  ) => {
    try {
      setIsLoading(true);
      setError(null);

      const ai = AIServiceFactory.createDefault();
      const response = await ai.createCompletion(messages, options);

      return response.content;
    } catch (err) {
      setError(err as Error);
      throw err;
    } finally {
      setIsLoading(false);
    }
  };

  const generateStream = async function* (
    messages: AIMessage[],
    options?: { temperature?: number; maxTokens?: number }
  ) {
    try {
      setIsLoading(true);
      setError(null);

      const ai = AIServiceFactory.createDefault();
      yield* ai.streamCompletion(messages, options);
    } catch (err) {
      setError(err as Error);
      throw err;
    } finally {
      setIsLoading(false);
    }
  };

  return {
    generateCompletion,
    generateStream,
    isLoading,
    error
  };
}
```

---

## Agentic AI Systems

### Agent Architecture

```typescript
// src/integrations/ai/agents/types.ts
export interface AgentConfig {
  name: string;
  description: string;
  systemPrompt: string;
  tools: Tool[];
  model?: string;
  temperature?: number;
}

export interface Tool {
  name: string;
  description: string;
  parameters: Record<string, any>;
  execute: (params: any) => Promise<any>;
}

export interface AgentMessage {
  role: 'system' | 'user' | 'assistant' | 'tool';
  content: string;
  toolCalls?: ToolCall[];
  toolCallId?: string;
}

export interface ToolCall {
  id: string;
  name: string;
  arguments: Record<string, any>;
}
```

### Base Agent Implementation

```typescript
// src/integrations/ai/agents/base-agent.ts
import { BaseAIProvider } from '../base';
import { AgentConfig, AgentMessage, Tool, ToolCall } from './types';

export class BaseAgent {
  private provider: BaseAIProvider;
  private config: AgentConfig;
  private conversationHistory: AgentMessage[] = [];

  constructor(provider: BaseAIProvider, config: AgentConfig) {
    this.provider = provider;
    this.config = config;

    // Initialize with system prompt
    this.conversationHistory.push({
      role: 'system',
      content: config.systemPrompt
    });
  }

  async run(userMessage: string): Promise<string> {
    // Add user message to history
    this.conversationHistory.push({
      role: 'user',
      content: userMessage
    });

    let iterations = 0;
    const maxIterations = 10;

    while (iterations < maxIterations) {
      iterations++;

      // Get completion from AI
      const response = await this.provider.createCompletion(
        this.conversationHistory,
        {
          model: this.config.model,
          temperature: this.config.temperature
        }
      );

      // Check if AI wants to use tools
      if (response.content.includes('TOOL_CALL:')) {
        // Parse tool calls
        const toolCalls = this.parseToolCalls(response.content);

        // Execute tools
        for (const toolCall of toolCalls) {
          const toolResult = await this.executeTool(toolCall);

          // Add tool result to history
          this.conversationHistory.push({
            role: 'tool',
            content: JSON.stringify(toolResult),
            toolCallId: toolCall.id
          });
        }

        // Continue loop to get next response
        continue;
      }

      // No more tool calls, return final answer
      this.conversationHistory.push({
        role: 'assistant',
        content: response.content
      });

      return response.content;
    }

    throw new Error('Max iterations reached');
  }

  private parseToolCalls(content: string): ToolCall[] {
    // Parse tool calls from response
    // Format: TOOL_CALL: toolName(param1=value1, param2=value2)
    const regex = /TOOL_CALL:\s*(\w+)\((.*?)\)/g;
    const toolCalls: ToolCall[] = [];

    let match;
    while ((match = regex.exec(content)) !== null) {
      const toolName = match[1];
      const paramsString = match[2];

      // Parse parameters
      const params: Record<string, any> = {};
      const paramPairs = paramsString.split(',');
      for (const pair of paramPairs) {
        const [key, value] = pair.split('=').map(s => s.trim());
        params[key] = value;
      }

      toolCalls.push({
        id: `tool_${Date.now()}_${Math.random()}`,
        name: toolName,
        arguments: params
      });
    }

    return toolCalls;
  }

  private async executeTool(toolCall: ToolCall): Promise<any> {
    const tool = this.config.tools.find(t => t.name === toolCall.name);

    if (!tool) {
      return {
        error: `Tool ${toolCall.name} not found`
      };
    }

    try {
      return await tool.execute(toolCall.arguments);
    } catch (error) {
      return {
        error: error instanceof Error ? error.message : 'Unknown error'
      };
    }
  }

  getHistory(): AgentMessage[] {
    return [...this.conversationHistory];
  }

  clearHistory(): void {
    this.conversationHistory = [{
      role: 'system',
      content: this.config.systemPrompt
    }];
  }
}
```

### Agent Tools

```typescript
// src/integrations/ai/agents/tools.ts
import { Tool } from './types';

export const searchTool: Tool = {
  name: 'search',
  description: 'Search the web for information',
  parameters: {
    query: { type: 'string', required: true }
  },
  execute: async (params) => {
    // Implement web search
    const response = await fetch(`https://api.search.com?q=${params.query}`);
    return await response.json();
  }
};

export const databaseQueryTool: Tool = {
  name: 'query_database',
  description: 'Query the database',
  parameters: {
    query: { type: 'string', required: true }
  },
  execute: async (params) => {
    // Execute database query
    return { results: [] };
  }
};

export const sendEmailTool: Tool = {
  name: 'send_email',
  description: 'Send an email',
  parameters: {
    to: { type: 'string', required: true },
    subject: { type: 'string', required: true },
    body: { type: 'string', required: true }
  },
  execute: async (params) => {
    // Send email
    return { success: true };
  }
};

export const calculateTool: Tool = {
  name: 'calculate',
  description: 'Perform mathematical calculations',
  parameters: {
    expression: { type: 'string', required: true }
  },
  execute: async (params) => {
    try {
      // Safe evaluation (use a proper math library in production)
      const result = eval(params.expression);
      return { result };
    } catch {
      return { error: 'Invalid expression' };
    }
  }
};
```

### Agent Store Integration

```typescript
// src/integrations/ai/agent-stores/langchain.ts
export class LangChainIntegration {
  private apiKey: string;

  constructor(apiKey?: string) {
    this.apiKey = apiKey || import.meta.env.VITE_LANGCHAIN_API_KEY;
  }

  async createAgent(config: {
    name: string;
    description: string;
    tools: string[];
  }) {
    // Create agent via LangChain API
    const response = await fetch('https://api.smith.langchain.com/agents', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${this.apiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(config)
    });

    return await response.json();
  }

  async runAgent(agentId: string, input: string) {
    const response = await fetch(
      `https://api.smith.langchain.com/agents/${agentId}/run`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ input })
      }
    );

    return await response.json();
  }
}
```

---

## Voice AI Integration

### Eleven Labs Integration

```typescript
// src/integrations/ai/voice/elevenlabs.ts
export class ElevenLabsService {
  private apiKey: string;
  private baseUrl = 'https://api.elevenlabs.io/v1';

  constructor(apiKey?: string) {
    this.apiKey = apiKey || import.meta.env.VITE_ELEVENLABS_API_KEY;
  }

  /**
   * Text to Speech
   */
  async textToSpeech(
    text: string,
    options?: {
      voiceId?: string;
      modelId?: string;
      stability?: number;
      similarityBoost?: number;
    }
  ): Promise<Blob> {
    const voiceId = options?.voiceId || 'default-voice-id';

    const response = await fetch(
      `${this.baseUrl}/text-to-speech/${voiceId}`,
      {
        method: 'POST',
        headers: {
          'xi-api-key': this.apiKey,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          text,
          model_id: options?.modelId || 'eleven_monolingual_v1',
          voice_settings: {
            stability: options?.stability ?? 0.5,
            similarity_boost: options?.similarityBoost ?? 0.75
          }
        })
      }
    );

    if (!response.ok) {
      throw new Error(`ElevenLabs error: ${response.statusText}`);
    }

    return await response.blob();
  }

  /**
   * List available voices
   */
  async getVoices() {
    const response = await fetch(`${this.baseUrl}/voices`, {
      headers: {
        'xi-api-key': this.apiKey
      }
    });

    if (!response.ok) {
      throw new Error(`ElevenLabs error: ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * Stream audio
   */
  async *streamTextToSpeech(
    text: string,
    options?: {
      voiceId?: string;
      modelId?: string;
    }
  ): AsyncIterableIterator<Uint8Array> {
    const voiceId = options?.voiceId || 'default-voice-id';

    const response = await fetch(
      `${this.baseUrl}/text-to-speech/${voiceId}/stream`,
      {
        method: 'POST',
        headers: {
          'xi-api-key': this.apiKey,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          text,
          model_id: options?.modelId || 'eleven_monolingual_v1'
        })
      }
    );

    if (!response.body) {
      throw new Error('No response body');
    }

    const reader = response.body.getReader();

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      yield value;
    }
  }
}
```

### Speech-to-Text (Whisper)

```typescript
// src/integrations/ai/voice/whisper.ts
import OpenAI from 'openai';

export class WhisperService {
  private client: OpenAI;

  constructor(apiKey?: string) {
    this.client = new OpenAI({
      apiKey: apiKey || import.meta.env.VITE_OPENAI_API_KEY
    });
  }

  /**
   * Transcribe audio to text
   */
  async transcribe(
    audioFile: File,
    options?: {
      language?: string;
      prompt?: string;
      temperature?: number;
    }
  ): Promise<string> {
    const response = await this.client.audio.transcriptions.create({
      file: audioFile,
      model: 'whisper-1',
      language: options?.language,
      prompt: options?.prompt,
      temperature: options?.temperature
    });

    return response.text;
  }

  /**
   * Translate audio to English
   */
  async translate(audioFile: File): Promise<string> {
    const response = await this.client.audio.translations.create({
      file: audioFile,
      model: 'whisper-1'
    });

    return response.text;
  }
}
```

### Voice Chat Component

```typescript
// src/components/features/ai/VoiceChat.tsx
import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Mic, MicOff, Volume2 } from 'lucide-react';
import { WhisperService } from '@/integrations/ai/voice/whisper';
import { ElevenLabsService } from '@/integrations/ai/voice/elevenlabs';
import { useAI } from '@/hooks/useAI';

export function VoiceChat() {
  const [isRecording, setIsRecording] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);

  const whisper = new WhisperService();
  const elevenlabs = new ElevenLabsService();
  const { generateCompletion } = useAI();

  const startRecording = async () => {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    const mediaRecorder = new MediaRecorder(stream);

    mediaRecorderRef.current = mediaRecorder;
    audioChunksRef.current = [];

    mediaRecorder.ondataavailable = (event) => {
      audioChunksRef.current.push(event.data);
    };

    mediaRecorder.onstop = async () => {
      const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
      await handleAudioComplete(audioBlob);
    };

    mediaRecorder.start();
    setIsRecording(true);
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
    }
  };

  const handleAudioComplete = async (audioBlob: Blob) => {
    try {
      // Convert blob to file
      const audioFile = new File([audioBlob], 'recording.webm', {
        type: 'audio/webm'
      });

      // Transcribe speech to text
      const transcript = await whisper.transcribe(audioFile);

      // Get AI response
      const response = await generateCompletion([
        { role: 'user', content: transcript }
      ]);

      // Convert response to speech
      const audioResponse = await elevenlabs.textToSpeech(response);

      // Play audio
      const audioUrl = URL.createObjectURL(audioResponse);
      const audio = new Audio(audioUrl);

      setIsPlaying(true);
      audio.onended = () => setIsPlaying(false);
      await audio.play();
    } catch (error) {
      console.error('Voice chat error:', error);
    }
  };

  return (
    <div className="flex items-center gap-4">
      <Button
        variant={isRecording ? 'destructive' : 'default'}
        size="lg"
        onClick={isRecording ? stopRecording : startRecording}
        disabled={isPlaying}
      >
        {isRecording ? <MicOff className="h-5 w-5" /> : <Mic className="h-5 w-5" />}
        {isRecording ? 'Stop Recording' : 'Start Recording'}
      </Button>

      {isPlaying && (
        <div className="flex items-center gap-2 text-muted-foreground">
          <Volume2 className="h-5 w-5 animate-pulse" />
          <span>Playing response...</span>
        </div>
      )}
    </div>
  );
}
```

---

## Knowledge Base Integration

### Vector Database (Pinecone)

```typescript
// src/integrations/ai/knowledge/pinecone.ts
import { Pinecone } from '@pinecone-database/pinecone';

export class PineconeKnowledgeBase {
  private client: Pinecone;
  private indexName: string;

  constructor(config?: { apiKey?: string; indexName?: string }) {
    this.client = new Pinecone({
      apiKey: config?.apiKey || import.meta.env.VITE_PINECONE_API_KEY
    });
    this.indexName = config?.indexName || 'knowledge-base';
  }

  /**
   * Add document to knowledge base
   */
  async addDocument(
    id: string,
    text: string,
    embedding: number[],
    metadata?: Record<string, any>
  ) {
    const index = this.client.index(this.indexName);

    await index.upsert([
      {
        id,
        values: embedding,
        metadata: {
          text,
          ...metadata
        }
      }
    ]);
  }

  /**
   * Search knowledge base
   */
  async search(
    queryEmbedding: number[],
    options?: {
      topK?: number;
      filter?: Record<string, any>;
    }
  ) {
    const index = this.client.index(this.indexName);

    const results = await index.query({
      vector: queryEmbedding,
      topK: options?.topK || 5,
      filter: options?.filter,
      includeMetadata: true
    });

    return results.matches.map(match => ({
      id: match.id,
      score: match.score,
      text: match.metadata?.text as string,
      metadata: match.metadata
    }));
  }

  /**
   * Delete document
   */
  async deleteDocument(id: string) {
    const index = this.client.index(this.indexName);
    await index.deleteOne(id);
  }
}
```

### RAG (Retrieval-Augmented Generation)

```typescript
// src/integrations/ai/rag.ts
import { BaseAIProvider } from './base';
import { PineconeKnowledgeBase } from './knowledge/pinecone';

export class RAGService {
  private ai: BaseAIProvider;
  private knowledgeBase: PineconeKnowledgeBase;

  constructor(ai: BaseAIProvider, knowledgeBase: PineconeKnowledgeBase) {
    this.ai = ai;
    this.knowledgeBase = knowledgeBase;
  }

  async query(question: string): Promise<string> {
    // 1. Generate embedding for question
    const questionEmbedding = await this.ai.createEmbedding(question);

    // 2. Search knowledge base
    const relevantDocs = await this.knowledgeBase.search(questionEmbedding, {
      topK: 3
    });

    // 3. Build context from retrieved documents
    const context = relevantDocs
      .map(doc => doc.text)
      .join('\n\n');

    // 4. Generate answer with context
    const response = await this.ai.createCompletion([
      {
        role: 'system',
        content: `You are a helpful assistant. Answer the question based on the following context:\n\n${context}`
      },
      {
        role: 'user',
        content: question
      }
    ]);

    return response.content;
  }
}
```

---

## N8N Workflow Automation

### N8N Webhook Integration

```typescript
// src/integrations/n8n/client.ts
export class N8NClient {
  private baseUrl: string;
  private apiKey?: string;

  constructor(config?: { baseUrl?: string; apiKey?: string }) {
    this.baseUrl = config?.baseUrl || import.meta.env.VITE_N8N_BASE_URL;
    this.apiKey = config?.apiKey || import.meta.env.VITE_N8N_API_KEY;
  }

  /**
   * Trigger webhook workflow
   */
  async triggerWebhook(
    webhookPath: string,
    data: Record<string, any>
  ): Promise<any> {
    const url = `${this.baseUrl}/webhook/${webhookPath}`;

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        ...(this.apiKey && { 'X-N8N-API-KEY': this.apiKey })
      },
      body: JSON.stringify(data)
    });

    if (!response.ok) {
      throw new Error(`N8N webhook error: ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * Get workflow execution status
   */
  async getExecutionStatus(executionId: string): Promise<any> {
    const response = await fetch(
      `${this.baseUrl}/api/v1/executions/${executionId}`,
      {
        headers: {
          'X-N8N-API-KEY': this.apiKey!
        }
      }
    );

    if (!response.ok) {
      throw new Error(`N8N API error: ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * List workflows
   */
  async listWorkflows(): Promise<any[]> {
    const response = await fetch(`${this.baseUrl}/api/v1/workflows`, {
      headers: {
        'X-N8N-API-KEY': this.apiKey!
      }
    });

    if (!response.ok) {
      throw new Error(`N8N API error: ${response.statusText}`);
    }

    const data = await response.json();
    return data.data;
  }
}
```

### Common N8N Workflows

```typescript
// src/integrations/n8n/workflows.ts
import { N8NClient } from './client';

export class N8NWorkflows {
  private client: N8NClient;

  constructor(client: N8NClient) {
    this.client = client;
  }

  /**
   * Trigger AI content generation workflow
   */
  async generateContent(params: {
    topic: string;
    type: 'blog' | 'social' | 'email';
    length: number;
  }) {
    return this.client.triggerWebhook('ai-content-generation', params);
  }

  /**
   * Trigger data enrichment workflow
   */
  async enrichData(params: {
    records: Array<Record<string, any>>;
    enrichmentType: 'company' | 'person' | 'location';
  }) {
    return this.client.triggerWebhook('data-enrichment', params);
  }

  /**
   * Trigger notification workflow
   */
  async sendNotification(params: {
    type: 'email' | 'slack' | 'sms';
    recipient: string;
    message: string;
  }) {
    return this.client.triggerWebhook('send-notification', params);
  }

  /**
   * Trigger QA automation workflow
   */
  async runQATests(params: {
    environment: 'dev' | 'staging' | 'prod';
    testSuite: string;
    prNumber?: string;
  }) {
    return this.client.triggerWebhook('qa-automation', params);
  }
}
```

---

## Cloud Provider Integration

### Environment Configuration

```typescript
// src/config/ai-providers.ts
export const aiProviderConfig = {
  // OpenAI
  openai: {
    apiKey: import.meta.env.VITE_OPENAI_API_KEY,
    models: {
      chat: 'gpt-4-turbo-preview',
      embedding: 'text-embedding-3-small',
      vision: 'gpt-4-vision-preview'
    }
  },

  // Anthropic
  anthropic: {
    apiKey: import.meta.env.VITE_ANTHROPIC_API_KEY,
    models: {
      chat: 'claude-3-5-sonnet-20241022'
    }
  },

  // Google AI
  google: {
    apiKey: import.meta.env.VITE_GOOGLE_AI_API_KEY,
    models: {
      chat: 'gemini-pro',
      vision: 'gemini-pro-vision'
    }
  },

  // Azure OpenAI
  azure: {
    apiKey: import.meta.env.VITE_AZURE_OPENAI_API_KEY,
    endpoint: import.meta.env.VITE_AZURE_OPENAI_ENDPOINT,
    deployment: import.meta.env.VITE_AZURE_OPENAI_DEPLOYMENT
  },

  // HuggingFace
  huggingface: {
    apiKey: import.meta.env.VITE_HUGGINGFACE_API_KEY
  },

  // Voice
  elevenlabs: {
    apiKey: import.meta.env.VITE_ELEVENLABS_API_KEY
  },

  // Knowledge Base
  pinecone: {
    apiKey: import.meta.env.VITE_PINECONE_API_KEY,
    environment: import.meta.env.VITE_PINECONE_ENVIRONMENT,
    indexName: import.meta.env.VITE_PINECONE_INDEX
  },

  // N8N
  n8n: {
    baseUrl: import.meta.env.VITE_N8N_BASE_URL,
    apiKey: import.meta.env.VITE_N8N_API_KEY
  }
};
```

### `.env.example`

```bash
# AI Providers
VITE_AI_PROVIDER=openai  # openai, anthropic, google, azure

# OpenAI
VITE_OPENAI_API_KEY=sk-...

# Anthropic
VITE_ANTHROPIC_API_KEY=sk-ant-...

# Google AI
VITE_GOOGLE_AI_API_KEY=...

# Azure OpenAI
VITE_AZURE_OPENAI_API_KEY=...
VITE_AZURE_OPENAI_ENDPOINT=https://...
VITE_AZURE_OPENAI_DEPLOYMENT=gpt-4

# HuggingFace
VITE_HUGGINGFACE_API_KEY=hf_...

# ElevenLabs (Voice)
VITE_ELEVENLABS_API_KEY=...

# Pinecone (Knowledge Base)
VITE_PINECONE_API_KEY=...
VITE_PINECONE_ENVIRONMENT=us-east-1-aws
VITE_PINECONE_INDEX=knowledge-base

# LangChain
VITE_LANGCHAIN_API_KEY=...

# N8N
VITE_N8N_BASE_URL=https://n8n.yourdomain.com
VITE_N8N_API_KEY=...
```

---

## Best Practices & Patterns

### ✅ DO

1. **Always use environment variables for API keys**
2. **Implement retry logic with exponential backoff**
3. **Cache AI responses when appropriate**
4. **Track token usage for cost monitoring**
5. **Implement rate limiting**
6. **Use streaming for long responses**
7. **Provide fallback providers**
8. **Log all AI interactions for debugging**
9. **Validate AI outputs before using them**
10. **Implement timeout handling**

### ❌ DON'T

1. **Don't hardcode API keys**
2. **Don't trust AI outputs without validation**
3. **Don't make unguarded API calls (use try-catch)**
4. **Don't ignore rate limits**
5. **Don't skip error handling**
6. **Don't expose API keys in frontend**
7. **Don't use AI for security-critical decisions without human review**

---

## Configuration Standards

### AI Feature Flags

```typescript
// src/config/ai-features.ts
export const aiFeatures = {
  chat: {
    enabled: true,
    providers: ['openai', 'anthropic'],
    defaultProvider: 'openai',
    streaming: true,
    maxTokens: 4096
  },

  voice: {
    enabled: true,
    tts: {
      provider: 'elevenlabs',
      defaultVoice: 'default-voice-id'
    },
    stt: {
      provider: 'whisper',
      language: 'en'
    }
  },

  rag: {
    enabled: true,
    vectorStore: 'pinecone',
    embeddingModel: 'text-embedding-3-small',
    topK: 5
  },

  agents: {
    enabled: true,
    maxIterations: 10,
    timeout: 30000
  }
};
```

---

## Guardrails for AI Agents

### ✅ ALWAYS
- Use the unified AI interface (BaseAIProvider)
- Store API keys in environment variables
- Implement error handling and retries
- Track and log token usage
- Validate AI outputs
- Use streaming for better UX
- Implement fallback providers
- Add rate limiting

### ❌ NEVER
- Hardcode API keys
- Trust AI outputs without validation
- Skip error handling
- Make unguarded API calls
- Expose sensitive data in prompts
- Use AI without user consent
- Skip cost tracking

---

**Last Updated**: 2024-01-07
**Maintained By**: Allia Engineering Team
**Version**: 1.0.0
