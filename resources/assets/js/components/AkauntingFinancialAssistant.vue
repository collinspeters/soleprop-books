<template>
    <div class="financial-assistant" :class="{ 'assistant-open': isOpen }">
        <!-- Toggle Button -->
        <button 
            @click="toggleAssistant" 
            class="assistant-toggle fixed right-4 top-1/2 transform -translate-y-1/2 bg-blue-600 hover:bg-blue-700 text-white p-3 rounded-full shadow-lg z-50 transition-all duration-300"
            :class="{ 'right-80': isOpen }"
        >
            <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-3.582 8-8 8a8.959 8.959 0 01-4.906-1.471L3 21l2.471-5.094A8.959 8.959 0 013 12c0-4.418 3.582-8 8-8s8 3.582 8 8z"></path>
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>

        <!-- Sidebar Panel -->
        <div 
            class="assistant-panel fixed right-0 top-0 h-full w-80 bg-white shadow-2xl transform transition-transform duration-300 z-40 border-l border-gray-200"
            :class="{ 'translate-x-0': isOpen, 'translate-x-full': !isOpen }"
        >
            <!-- Header -->
            <div class="assistant-header bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold">Financial Assistant</h3>
                </div>
                <button @click="clearChat" class="text-white hover:text-gray-200 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>

            <!-- Quick Questions -->
            <div class="quick-questions p-4 border-b border-gray-200" v-if="messages.length === 0">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Quick Questions:</h4>
                <div class="space-y-2">
                    <button 
                        v-for="question in quickQuestions" 
                        :key="question"
                        @click="askQuestion(question)"
                        class="w-full text-left text-sm bg-gray-50 hover:bg-gray-100 p-3 rounded-lg transition-colors duration-200"
                    >
                        {{ question }}
                    </button>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="chat-messages flex-1 overflow-y-auto p-4 space-y-4" style="height: calc(100vh - 200px);">
                <div 
                    v-for="(message, index) in messages" 
                    :key="index"
                    class="message"
                    :class="{ 'user-message': message.type === 'user', 'assistant-message': message.type === 'assistant' }"
                >
                    <div v-if="message.type === 'user'" class="flex justify-end">
                        <div class="bg-blue-600 text-white p-3 rounded-lg max-w-xs">
                            {{ message.content }}
                        </div>
                    </div>
                    <div v-else class="flex justify-start">
                        <div class="bg-gray-100 text-gray-800 p-3 rounded-lg max-w-xs">
                            <div v-if="message.loading" class="flex items-center space-x-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                                <span class="text-sm">Analyzing your finances...</span>
                            </div>
                            <div v-else v-html="formatMessage(message.content)"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input Area -->
            <div class="chat-input p-4 border-t border-gray-200">
                <div class="flex space-x-2">
                    <input 
                        v-model="currentMessage"
                        @keypress.enter="sendMessage"
                        :disabled="isLoading"
                        type="text" 
                        placeholder="Ask about your finances..."
                        class="flex-1 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                    <button 
                        @click="sendMessage"
                        :disabled="isLoading || !currentMessage.trim()"
                        class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white p-2 rounded-lg transition-colors duration-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Overlay -->
        <div 
            v-if="isOpen" 
            @click="toggleAssistant"
            class="fixed inset-0 bg-black bg-opacity-25 z-30"
        ></div>
    </div>
</template>

<script>
export default {
    name: 'AkauntingFinancialAssistant',
    
    data() {
        return {
            isOpen: false,
            currentMessage: '',
            messages: [],
            isLoading: false,
            quickQuestions: [
                "What were my biggest expenses last month?",
                "Can you give me a summary of income and expenses for Q1?",
                "Are there any uncategorized transactions I should review?",
                "Show me my spending trends for the past 6 months",
                "What's my current cash flow situation?",
                "Which categories am I overspending in?"
            ]
        }
    },

    methods: {
        toggleAssistant() {
            this.isOpen = !this.isOpen;
        },

        clearChat() {
            this.messages = [];
            this.currentMessage = '';
        },

        askQuestion(question) {
            this.currentMessage = question;
            this.sendMessage();
        },

        async sendMessage() {
            if (!this.currentMessage.trim() || this.isLoading) return;

            const userMessage = this.currentMessage.trim();
            this.currentMessage = '';

            // Add user message
            this.messages.push({
                type: 'user',
                content: userMessage,
                timestamp: new Date()
            });

            // Add loading message
            const loadingMessage = {
                type: 'assistant',
                content: '',
                loading: true,
                timestamp: new Date()
            };
            this.messages.push(loadingMessage);

            this.isLoading = true;

            try {
                const response = await this.callAssistantAPI(userMessage);
                
                // Remove loading message and add response
                this.messages.pop();
                this.messages.push({
                    type: 'assistant',
                    content: response,
                    loading: false,
                    timestamp: new Date()
                });
            } catch (error) {
                console.error('Assistant API error:', error);
                
                // Remove loading message and add error
                this.messages.pop();
                this.messages.push({
                    type: 'assistant',
                    content: 'I apologize, but I encountered an error while analyzing your financial data. Please try again later.',
                    loading: false,
                    timestamp: new Date()
                });
            } finally {
                this.isLoading = false;
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            }
        },

        async callAssistantAPI(message) {
            const response = await axios.post('/api/financial-assistant', {
                message: message,
                context: {
                    company_id: window.Laravel.company_id || 1
                }
            });

            return response.data.response;
        },

        formatMessage(content) {
            if (!content) return '';
            
            // Convert markdown-like formatting to HTML
            return content
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>')
                .replace(/- (.*?)(?=\n|$)/g, '• $1');
        },

        scrollToBottom() {
            const chatMessages = this.$el.querySelector('.chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
    },

    mounted() {
        // Listen for keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                this.toggleAssistant();
            }
        });
    }
}
</script>

<style scoped>
.financial-assistant {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.assistant-toggle {
    transition: all 0.3s ease;
}

.assistant-panel {
    box-shadow: -10px 0 25px -5px rgba(0, 0, 0, 0.1);
}

.chat-messages {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f7fafc;
}

.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: #f7fafc;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: #a0aec0;
}

.user-message {
    animation: slideInRight 0.3s ease;
}

.assistant-message {
    animation: slideInLeft 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes slideInLeft {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.quick-questions button:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>