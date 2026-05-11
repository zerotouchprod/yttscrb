{{-- Keep in sync with resources/js/App.vue footer section --}}
<footer class="w-full border-t border-slate-800 bg-[#0f172a] mt-20">
  <div class="max-w-4xl mx-auto px-4 py-8 sm:py-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8 text-sm">

      <div class="col-span-2 md:col-span-1">
        <span class="text-xl font-bold text-slate-200 tracking-tight">TubeSum</span>
        <p class="mt-2 text-slate-500">Save hours of watching. Get the text in seconds.</p>
      </div>

      <div>
        <h3 class="font-semibold text-slate-100 mb-3">Product</h3>
        <ul class="space-y-2 text-slate-400">
          <li><a href="/pricing" class="hover:text-blue-400 transition-colors">Pricing</a></li>
          <li><a href="/history" class="hover:text-blue-400 transition-colors">Public Library</a></li>
          <li><a href="#" class="hover:text-blue-400 transition-colors">Chrome Extension <span class="text-[10px] bg-slate-800 border border-slate-700 text-slate-300 px-1.5 py-0.5 rounded ml-1">Soon</span></a></li>
        </ul>
      </div>

      <div>
        <h3 class="font-semibold text-slate-100 mb-3">Legal</h3>
        <ul class="space-y-2 text-slate-400">
          <li><a href="/terms" class="hover:text-blue-400 transition-colors">Terms of Service</a></li>
          <li><a href="/privacy" class="hover:text-blue-400 transition-colors">Privacy Policy</a></li>
          <li><a href="/dmca" class="hover:text-red-400 transition-colors">DMCA / Removal</a></li>
        </ul>
      </div>

      <div>
        <h3 class="font-semibold text-slate-100 mb-3">Connect</h3>
        <ul class="space-y-2 text-slate-400">
          <li><a href="https://x.com/tubesum" target="_blank" rel="noopener noreferrer" class="hover:text-blue-400 transition-colors">Twitter (X)</a></li>
          <li><a href="/contact" class="hover:text-blue-400 transition-colors">Contact Support</a></li>
        </ul>
      </div>

    </div>

    <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-slate-500">
      <p>&copy; {{ date('Y') }} TubeSum.app. All rights reserved.</p>
      <p>Built with Laravel & Tailwind</p>
    </div>
  </div>
</footer>
