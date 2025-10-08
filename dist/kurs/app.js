// Course Application - Werbekoordinator
// TTS, Navigation, Progress Tracking

class CourseApp {
    constructor() {
        this.currentModule = null;
        this.currentPage = null;
        this.courseStructure = {
            '01': { pages: ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', 'end'], name: 'Sens Werbekoordinatora' },
            '02': { pages: ['01', '02', '03', '04', '05', 'end'], name: 'Werbekoordinator w praktyce' },
            'TEST': { pages: ['index'], name: 'Test końcowy' }
        };
        this.progress = this.loadProgress();
        this.ttsSupported = 'speechSynthesis' in window;
        this.voices = [];
        this.currentUtterance = null;
        this.isPlaying = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupTTS();
        this.updateProgressDisplay();
        this.restoreLastPosition();
        this.setupKeyboardShortcuts();
    }

    setupEventListeners() {
        // Sidebar navigation
        document.querySelectorAll('.modules-list a').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const module = link.dataset.module;
                const page = link.dataset.page;
                this.loadPage(module, page);
            });
        });

        // Navigation buttons
        document.getElementById('prev-btn').addEventListener('click', () => this.navigatePrev());
        document.getElementById('next-btn').addEventListener('click', () => this.navigateNext());

        // Audio controls
        document.getElementById('play-btn').addEventListener('click', () => this.playAudio());
        document.getElementById('pause-btn').addEventListener('click', () => this.pauseAudio());
        document.getElementById('stop-btn').addEventListener('click', () => this.stopAudio());
        document.getElementById('replay-btn').addEventListener('click', () => this.replayAudio());
        
        document.getElementById('speed-select').addEventListener('change', (e) => {
            if (this.currentUtterance) {
                this.currentUtterance.rate = parseFloat(e.target.value);
            }
        });

        document.getElementById('voice-select').addEventListener('change', (e) => {
            if (this.currentUtterance) {
                const selectedVoice = this.voices.find(v => v.name === e.target.value);
                if (selectedVoice) {
                    this.currentUtterance.voice = selectedVoice;
                }
            }
        });

        // Sidebar toggle for mobile
        const toggleBtn = document.getElementById('toggle-sidebar');
        const sidebar = document.getElementById('sidebar');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar on mobile when link is clicked
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.modules-list a').forEach(link => {
                link.addEventListener('click', () => {
                    sidebar.classList.remove('open');
                });
            });
        }
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Don't trigger if user is typing in an input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            switch(e.key.toLowerCase()) {
                case ' ':
                    e.preventDefault();
                    if (this.isPlaying) {
                        this.pauseAudio();
                    } else {
                        this.playAudio();
                    }
                    break;
                case 'n':
                    e.preventDefault();
                    this.navigateNext();
                    break;
                case 'p':
                    e.preventDefault();
                    this.navigatePrev();
                    break;
            }
        });
    }

    setupTTS() {
        if (!this.ttsSupported) {
            this.showTTSNotSupported();
            return;
        }

        // Load voices
        const loadVoices = () => {
            this.voices = speechSynthesis.getVoices();
            const polishVoices = this.voices.filter(v => v.lang.startsWith('pl'));
            const voiceSelect = document.getElementById('voice-select');
            
            voiceSelect.innerHTML = '';
            
            if (polishVoices.length > 0) {
                polishVoices.forEach(voice => {
                    const option = document.createElement('option');
                    option.value = voice.name;
                    option.textContent = `${voice.name} (${voice.lang})`;
                    voiceSelect.appendChild(option);
                });
            } else {
                // Use any available voice
                this.voices.slice(0, 5).forEach(voice => {
                    const option = document.createElement('option');
                    option.value = voice.name;
                    option.textContent = `${voice.name} (${voice.lang})`;
                    voiceSelect.appendChild(option);
                });
            }
        };

        loadVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) {
            speechSynthesis.onvoiceschanged = loadVoices;
        }
    }

    showTTSNotSupported() {
        const audioControls = document.querySelector('.audio-controls');
        audioControls.innerHTML = `
            <div class="warning-box">
                <p>⚠️ Twoja przeglądarka nie obsługuje funkcji lektora (Text-to-Speech). 
                Możesz nadal korzystać z kursu w trybie czytania lub dodać pliki MP3 do katalogu audio/.</p>
            </div>
        `;
    }

    async loadPage(module, page) {
        this.stopAudio();
        this.currentModule = module;
        this.currentPage = page;

        const contentFrame = document.getElementById('content-frame');
        contentFrame.classList.add('loading');

        try {
            // Try to load MP3 first
            const audioPath = `audio/${module}-${page}.mp3`;
            const hasAudio = await this.checkAudioFile(audioPath);

            // Load HTML content
            const response = await fetch(`modules/${module}/${page}.html`);
            if (!response.ok) {
                throw new Error(`Nie można załadować strony: ${response.status}`);
            }
            
            const html = await response.text();
            contentFrame.innerHTML = html;

            // Update navigation
            this.updateNavigation();
            this.updateSidebarHighlight();
            
            // Save position
            this.savePosition(module, page);
            
            // Mark as completed
            this.markPageCompleted(module, page);

            // Setup audio
            if (hasAudio) {
                this.setupMP3Audio(audioPath);
            } else if (this.ttsSupported) {
                this.setupTTSAudio();
            }

            // Handle test if on test page
            if (module === 'TEST') {
                this.setupTest();
            }

            // Scroll to top
            window.scrollTo(0, 0);

        } catch (error) {
            contentFrame.innerHTML = `
                <div class="warning-box">
                    <h3>⚠️ Błąd ładowania</h3>
                    <p>Nie można załadować strony: ${error.message}</p>
                    <p>Upewnij się, że plik modules/${module}/${page}.html istnieje.</p>
                </div>
            `;
        } finally {
            contentFrame.classList.remove('loading');
        }
    }

    async checkAudioFile(path) {
        try {
            const response = await fetch(path, { method: 'HEAD' });
            return response.ok;
        } catch {
            return false;
        }
    }

    setupMP3Audio(audioPath) {
        // Replace audio controls with HTML5 audio player
        const audioControls = document.querySelector('.audio-controls');
        audioControls.innerHTML = `
            <audio controls style="width: 100%;">
                <source src="${audioPath}" type="audio/mpeg">
                Twoja przeglądarka nie obsługuje odtwarzania audio.
            </audio>
        `;
    }

    setupTTSAudio() {
        const contentFrame = document.getElementById('content-frame');
        const textContent = contentFrame.innerText;

        this.currentUtterance = new SpeechSynthesisUtterance(textContent);
        this.currentUtterance.lang = 'pl-PL';
        this.currentUtterance.rate = parseFloat(document.getElementById('speed-select').value);
        
        const selectedVoiceName = document.getElementById('voice-select').value;
        const selectedVoice = this.voices.find(v => v.name === selectedVoiceName);
        if (selectedVoice) {
            this.currentUtterance.voice = selectedVoice;
        }

        this.currentUtterance.onstart = () => {
            this.isPlaying = true;
            document.getElementById('play-btn').style.display = 'none';
            document.getElementById('pause-btn').style.display = 'inline-block';
        };

        this.currentUtterance.onend = () => {
            this.isPlaying = false;
            document.getElementById('play-btn').style.display = 'inline-block';
            document.getElementById('pause-btn').style.display = 'none';
        };

        this.currentUtterance.onerror = (e) => {
            console.error('TTS error:', e);
            this.isPlaying = false;
        };
    }

    playAudio() {
        if (!this.ttsSupported || !this.currentUtterance) return;
        
        speechSynthesis.cancel(); // Clear any pending speech
        speechSynthesis.speak(this.currentUtterance);
    }

    pauseAudio() {
        if (!this.ttsSupported) return;
        speechSynthesis.pause();
        this.isPlaying = false;
        document.getElementById('play-btn').style.display = 'inline-block';
        document.getElementById('pause-btn').style.display = 'none';
    }

    stopAudio() {
        if (!this.ttsSupported) return;
        speechSynthesis.cancel();
        this.isPlaying = false;
        document.getElementById('play-btn').style.display = 'inline-block';
        document.getElementById('pause-btn').style.display = 'none';
    }

    replayAudio() {
        this.stopAudio();
        setTimeout(() => this.playAudio(), 100);
    }

    updateNavigation() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const pageIndicator = document.getElementById('page-indicator');

        const prev = this.getPreviousPage();
        const next = this.getNextPage();

        prevBtn.disabled = !prev;
        nextBtn.disabled = !next;

        // Update page indicator
        const moduleInfo = this.courseStructure[this.currentModule];
        const currentIndex = moduleInfo.pages.indexOf(this.currentPage);
        pageIndicator.textContent = `Strona ${currentIndex + 1} z ${moduleInfo.pages.length}`;
    }

    getPreviousPage() {
        const moduleInfo = this.courseStructure[this.currentModule];
        const currentIndex = moduleInfo.pages.indexOf(this.currentPage);
        
        if (currentIndex > 0) {
            return { module: this.currentModule, page: moduleInfo.pages[currentIndex - 1] };
        }
        
        // Go to previous module
        const modules = Object.keys(this.courseStructure);
        const moduleIndex = modules.indexOf(this.currentModule);
        if (moduleIndex > 0) {
            const prevModule = modules[moduleIndex - 1];
            const prevModuleInfo = this.courseStructure[prevModule];
            return { module: prevModule, page: prevModuleInfo.pages[prevModuleInfo.pages.length - 1] };
        }
        
        return null;
    }

    getNextPage() {
        const moduleInfo = this.courseStructure[this.currentModule];
        const currentIndex = moduleInfo.pages.indexOf(this.currentPage);
        
        if (currentIndex < moduleInfo.pages.length - 1) {
            return { module: this.currentModule, page: moduleInfo.pages[currentIndex + 1] };
        }
        
        // Go to next module
        const modules = Object.keys(this.courseStructure);
        const moduleIndex = modules.indexOf(this.currentModule);
        if (moduleIndex < modules.length - 1) {
            const nextModule = modules[moduleIndex + 1];
            return { module: nextModule, page: this.courseStructure[nextModule].pages[0] };
        }
        
        return null;
    }

    navigatePrev() {
        const prev = this.getPreviousPage();
        if (prev) {
            this.loadPage(prev.module, prev.page);
        }
    }

    navigateNext() {
        const next = this.getNextPage();
        if (next) {
            this.loadPage(next.module, next.page);
        }
    }

    updateSidebarHighlight() {
        document.querySelectorAll('.modules-list a').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.module === this.currentModule && link.dataset.page === this.currentPage) {
                link.classList.add('active');
            }
        });
    }

    loadProgress() {
        const saved = localStorage.getItem('course-progress');
        return saved ? JSON.parse(saved) : { completed: {}, lastPosition: null };
    }

    saveProgress() {
        localStorage.setItem('course-progress', JSON.stringify(this.progress));
    }

    savePosition(module, page) {
        this.progress.lastPosition = { module, page };
        this.saveProgress();
    }

    markPageCompleted(module, page) {
        const key = `${module}-${page}`;
        if (!this.progress.completed[key]) {
            this.progress.completed[key] = true;
            this.saveProgress();
            this.updateProgressDisplay();
            this.updateCompletedMarks();
        }
    }

    updateCompletedMarks() {
        document.querySelectorAll('.modules-list a').forEach(link => {
            const key = `${link.dataset.module}-${link.dataset.page}`;
            if (this.progress.completed[key]) {
                link.classList.add('completed');
            }
        });
    }

    updateProgressDisplay() {
        const totalPages = Object.values(this.courseStructure).reduce((sum, module) => sum + module.pages.length, 0);
        const completedPages = Object.keys(this.progress.completed).length;
        const percentage = Math.round((completedPages / totalPages) * 100);

        document.getElementById('overall-progress').style.width = percentage + '%';
        document.getElementById('overall-progress').setAttribute('aria-valuenow', percentage);
        document.getElementById('progress-text').textContent = `Postęp: ${percentage}%`;
        
        this.updateCompletedMarks();
    }

    restoreLastPosition() {
        if (this.progress.lastPosition) {
            const { module, page } = this.progress.lastPosition;
            this.loadPage(module, page);
        }
    }

    // Test functionality
    setupTest() {
        const submitBtn = document.getElementById('submit-test');
        const retryBtn = document.getElementById('retry-test');
        const playQuestionBtn = document.getElementById('play-question');

        if (submitBtn) {
            submitBtn.addEventListener('click', () => this.checkTestAnswers());
        }

        if (retryBtn) {
            retryBtn.addEventListener('click', () => this.resetTest());
        }

        if (playQuestionBtn && this.ttsSupported) {
            playQuestionBtn.addEventListener('click', () => {
                const currentQuestion = document.querySelector('.question:not(.answered)');
                if (currentQuestion) {
                    const text = currentQuestion.innerText;
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'pl-PL';
                    speechSynthesis.speak(utterance);
                }
            });
        }
    }

    checkTestAnswers() {
        const questions = document.querySelectorAll('.question');
        let correct = 0;
        let total = questions.length;

        questions.forEach((question, index) => {
            const selected = question.querySelector('input[type="radio"]:checked');
            if (selected && selected.value === 'correct') {
                correct++;
            }
        });

        const percentage = Math.round((correct / total) * 100);
        const passed = percentage >= 80;

        const resultDiv = document.getElementById('test-result');
        resultDiv.classList.remove('passed', 'failed');
        resultDiv.classList.add(passed ? 'passed' : 'failed');
        
        resultDiv.innerHTML = `
            <h2>${passed ? '✅ Gratulacje!' : '❌ Nie zaliczono'}</h2>
            <p>Twój wynik: ${correct} z ${total} (${percentage}%)</p>
            ${passed 
                ? '<p>Świetna robota! Ukończyłeś kurs Werbekoordinator.</p>' 
                : '<p>Próg zaliczenia to 80%. Spróbuj ponownie!</p>'}
        `;

        if (passed) {
            this.markPageCompleted('TEST', 'index');
        }

        document.getElementById('submit-test').style.display = 'none';
        document.getElementById('retry-test').style.display = 'inline-block';
    }

    resetTest() {
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.checked = false;
        });
        
        const resultDiv = document.getElementById('test-result');
        resultDiv.innerHTML = '';
        resultDiv.classList.remove('passed', 'failed');

        document.getElementById('submit-test').style.display = 'inline-block';
        document.getElementById('retry-test').style.display = 'none';
    }
}

// Initialize the app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.courseApp = new CourseApp();
});
