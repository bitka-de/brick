@extends('layouts.app')

@section('content')
<div class="bg-white rounded-lg shadow-lg p-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ $title ?? 'Contact Us' }}</h1>
    
    <div class="grid md:grid-cols-2 gap-8">
        <!-- Contact Form -->
        <div>
            <form id="contactForm" method="POST" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                    <input type="text" 
                           id="subject" 
                           name="subject" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="message" 
                              name="message" 
                              rows="4" 
                              required 
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                </div>
                
                <div>
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Send Message
                    </button>
                </div>
            </form>
            
            <!-- Response Messages -->
            <div id="response" class="mt-4 hidden"></div>
        </div>
        
        <!-- Contact Information -->
        <div>
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Get in Touch</h2>
            <p class="text-gray-600 mb-6">
                Have a question about Brick Framework? We'd love to hear from you. 
                Send us a message and we'll respond as soon as possible.
            </p>
            
            <div class="space-y-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                    </svg>
                    <span class="text-gray-600">support@brickframework.dev</span>
                </div>
                
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd"></path>
                    </svg>
                    <a href="https://github.com/bitka-de/brick" class="text-blue-600 hover:text-blue-800">GitHub Repository</a>
                </div>
                
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.499-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.497-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.148.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-gray-600">Documentation & Guides</span>
                </div>
            </div>
            
            <!-- Quick Links -->
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Links</h3>
                <div class="space-y-2">
                    <a href="/" class="block text-blue-600 hover:text-blue-800">← Back to Homepage</a>
                    <a href="/dashboard" class="block text-blue-600 hover:text-blue-800">View Dashboard</a>
                    <a href="/about" class="block text-blue-600 hover:text-blue-800">About Framework</a>
                    <a href="/api/health" class="block text-blue-600 hover:text-blue-800">API Health Check</a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('contactForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    const responseDiv = document.getElementById('response');
    
    try {
        const response = await fetch('/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        responseDiv.className = 'mt-4 p-4 rounded-md ' + 
            (result.success ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800');
        responseDiv.textContent = result.message || 'Something went wrong';
        responseDiv.classList.remove('hidden');
        
        if (result.success) {
            this.reset();
        }
        
        if (result.errors) {
            let errorText = 'Please fix the following errors:\n';
            for (const [field, error] of Object.entries(result.errors)) {
                errorText += `• ${error}\n`;
            }
            responseDiv.textContent = errorText;
        }
        
    } catch (error) {
        responseDiv.className = 'mt-4 p-4 rounded-md bg-red-50 border border-red-200 text-red-800';
        responseDiv.textContent = 'Network error. Please try again.';
        responseDiv.classList.remove('hidden');
    }
});
</script>
@endpush
@endsection