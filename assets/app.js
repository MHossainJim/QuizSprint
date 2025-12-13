// QuizSprint JavaScript Application

// Global variables
let currentQuestion = null;
let quizTimer = null;
let leaderboardPoll = null;
let statusPoll = null;

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize quiz if on quiz page
    if (window.quizData) {
        initializeQuiz();
    }
    
    // Initialize teacher dashboard features
    if (document.querySelector('.room-card')) {
        initializeTeacherDashboard();
    }
    
    // Auto-focus first input on auth pages
    const firstInput = document.querySelector('input[type="text"], input[type="email"]');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Room code input formatting
    const roomCodeInput = document.querySelector('input[name="room_code"]');
    if (roomCodeInput) {
        roomCodeInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }
});

// Quiz functionality
function initializeQuiz() {
    console.log('Initializing quiz...', window.quizData);
    
    if (window.quizData.status === 'live') {
        startQuizTimer();
        loadNextQuestion();
    } else if (window.quizData.status === 'waiting') {
        pollRoomStatus();
    }
    
    // Start leaderboard polling
    startLeaderboardPolling();
}

function startQuizTimer() {
    const timerElement = document.getElementById('timer');
    if (!timerElement || !window.quizData.startTime) return;
    
    quizTimer = setInterval(function() {
        const now = Date.now();
        const elapsed = now - window.quizData.startTime;
        const remaining = Math.max(0, window.quizData.duration - elapsed);
        
        if (remaining <= 0) {
            clearInterval(quizTimer);
            timerElement.textContent = '00:00';
            handleQuizEnd();
            return;
        }
        
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        // Warning color when less than 1 minute
        if (remaining < 60000) {
            timerElement.style.color = '#dc3545';
        }
    }, 1000);
}

function loadNextQuestion() {
    fetch(`api/get_question.php?room_id=${window.quizData.roomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayQuestion(data.question);
                updateProgress(data.progress);
            } else {
                if (data.message === 'No more questions') {
                    handleQuizEnd();
                } else {
                    console.error('Error loading question:', data.message);
                }
            }
        })
        .catch(error => {
            console.error('Error loading question:', error);
        });
}

function displayQuestion(question) {
    const container = document.getElementById('questionContainer');
    if (!container) return;
    
    currentQuestion = question;
    
    container.innerHTML = `
        <div class="question-header">
            <div class="question-number">Question ${window.quizData.currentQuestion || 1} of ${window.quizData.totalQuestions || '?'}</div>
            <div class="question-text">${escapeHtml(question.text)}</div>
        </div>
        <div class="options-grid">
            ${Object.entries(question.options).map(([num, text]) => `
                <div class="option-card" onclick="selectOption(${num})" data-option="${num}">
                    <span class="option-label">${String.fromCharCode(64 + parseInt(num))}</span>
                    <span class="option-text">${escapeHtml(text)}</span>
                </div>
            `).join('')}
        </div>
    `;
}

function selectOption(optionNum) {
    if (!currentQuestion) return;
    
    // Disable all option cards
    const options = document.querySelectorAll('.option-card');
    options.forEach(option => {
        option.style.pointerEvents = 'none';
        option.classList.remove('selected');
    });
    
    // Mark selected option
    const selectedOption = document.querySelector(`[data-option="${optionNum}"]`);
    if (selectedOption) {
        selectedOption.classList.add('selected');
    }
    
    // Submit answer
    submitAnswer(optionNum);
}

function submitAnswer(selectedOption) {
    fetch('api/submit_answer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            room_id: window.quizData.roomId,
            question_id: currentQuestion.id,
            selected_option: selectedOption
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAnswerResult(data.correct_option, data.selected_option, data.correct);
            // Move to next question after delay
            setTimeout(() => {
                window.quizData.currentQuestion = (window.quizData.currentQuestion || 1) + 1;
                loadNextQuestion();
            }, 1500);
        } else {
            console.error('Error submitting answer:', data.message);
        }
    })
    .catch(error => {
        console.error('Error submitting answer:', error);
    });
}

function showAnswerResult(correctOption, selectedOption, isCorrect) {
    const options = document.querySelectorAll('.option-card');
    
    options.forEach(option => {
        const optionNum = parseInt(option.getAttribute('data-option'));
        
        if (optionNum === correctOption) {
            option.classList.add('correct');
        } else if (optionNum === selectedOption && !isCorrect) {
            option.classList.add('incorrect');
        }
    });
}

function updateProgress(current) {
    const progressElement = document.getElementById('progress');
    if (progressElement) {
        progressElement.textContent = current;
    }
}

function handleQuizEnd() {
    const quizScreen = document.getElementById('quizScreen');
    if (quizScreen) {
        quizScreen.innerHTML = `
            <div class="finished-screen">
                <div class="finished-content">
                    <h2>🏁 Quiz Completed</h2>
                    <p>Thanks for participating! Check your final score on the leaderboard below.</p>
                </div>
            </div>
        `;
    }
    
    // Update status
    const statusElement = document.getElementById('quizStatus');
    if (statusElement) {
        statusElement.textContent = 'Finished';
        statusElement.className = 'status-badge status-finished';
    }
}

// Leaderboard functionality
function startLeaderboardPolling() {
    if (!window.quizData) return;
    
    updateLeaderboard(); // Initial load
    
    leaderboardPoll = setInterval(updateLeaderboard, 3000); // Poll every 3 seconds
}

function updateLeaderboard() {
    fetch(`api/get_leaderboard.php?room_id=${window.quizData.roomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeaderboard(data.leaderboard);
            } else {
                console.error('Error loading leaderboard:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading leaderboard:', error);
        });
}

function displayLeaderboard(leaderboard) {
    const container = document.getElementById('leaderboard');
    if (!container) return;
    
    if (leaderboard.length === 0) {
        container.innerHTML = '<div class="loading">No students yet...</div>';
        return;
    }
    
    container.innerHTML = leaderboard.map(student => `
        <div class="leaderboard-item ${student.is_current_user ? 'current-user' : ''} rank-${student.rank}">
            <div class="rank-number ${student.rank <= 3 ? `rank-${student.rank}` : ''}">${student.rank <= 3 ? '' : student.rank}</div>
            <div class="student-name">${escapeHtml(student.name)}</div>
            <div class="student-progress">${student.answered}/${student.total_questions}</div>
            <div class="student-score">${student.score} pts</div>
        </div>
    `).join('');
}

// Room status polling for waiting students
function pollRoomStatus() {
    if (!window.quizData) return;
    
    statusPoll = setInterval(function() {
        fetch(`api/room_status.php?room_id=${window.quizData.roomId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status !== window.quizData.status) {
                    if (data.status === 'live') {
                        clearInterval(statusPoll);
                        window.location.reload(); // Reload to start quiz
                    }
                }
            })
            .catch(error => {
                console.error('Error polling room status:', error);
            });
    }, 2000);
}

// Teacher dashboard functionality
function initializeTeacherDashboard() {
    // Add click handlers for start quiz buttons
    const startButtons = document.querySelectorAll('[onclick^="startQuiz"]');
    startButtons.forEach(button => {
        const roomId = button.getAttribute('onclick').match(/\d+/)[0];
        button.addEventListener('click', function(e) {
            e.preventDefault();
            startQuiz(roomId);
        });
    });
}

function startQuiz(roomId) {
    if (!confirm('Are you sure you want to start the quiz? Students will begin answering immediately.')) {
        return;
    }
    
    fetch('api/start_quiz.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            room_id: roomId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error starting quiz: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error starting quiz:', error);
        alert('Error starting quiz. Please try again.');
    });
}

// Utility functions
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Join room functionality for students
function joinRoom(roomCode) {
    return fetch('api/join_room.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            room_code: roomCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `quiz.php?room=${data.room_id}`;
        } else {
            throw new Error(data.message);
        }
    });
}

// Form enhancements
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced room code input
    const roomCodeInputs = document.querySelectorAll('input[name="room_code"]');
    roomCodeInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 6) {
                value = value.substring(0, 6);
            }
            e.target.value = value;
        });
        
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = e.target.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    });
    
    // Auto-submit forms on Enter key
    const autoSubmitInputs = document.querySelectorAll('input[type="email"], input[type="password"]');
    autoSubmitInputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = e.target.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    });
    
    // Question form validation
    const questionForm = document.querySelector('.question-form');
    if (questionForm) {
        questionForm.addEventListener('submit', function(e) {
            const options = [
                questionForm.option1.value.trim(),
                questionForm.option2.value.trim(),
                questionForm.option3.value.trim(),
                questionForm.option4.value.trim()
            ];
            
            // Check for duplicate options
            const uniqueOptions = [...new Set(options)];
            if (uniqueOptions.length !== options.length) {
                e.preventDefault();
                alert('Please ensure all options are unique.');
                return;
            }
            
            // Check if any option is empty
            if (options.some(option => option === '')) {
                e.preventDefault();
                alert('Please fill in all option fields.');
                return;
            }
        });
    }
});

// Cleanup intervals when page is unloaded
window.addEventListener('beforeunload', function() {
    if (quizTimer) clearInterval(quizTimer);
    if (leaderboardPoll) clearInterval(leaderboardPoll);
    if (statusPoll) clearInterval(statusPoll);
});

// Error handling for fetch requests
function handleFetchError(response) {
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response;
}

// Add global error handler for unhandled promise rejections
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
});

// Notification system
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '1000';
    notification.style.minWidth = '300px';
    
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Live features for teachers
function initTeacherLiveView() {
    if (!window.location.href.includes('manage_room.php')) return;
    
    const roomId = new URLSearchParams(window.location.search).get('id');
    if (!roomId) return;
    
    // Poll for live updates every 5 seconds
    setInterval(() => {
        updateLiveStats(roomId);
    }, 5000);
}

function updateLiveStats(roomId) {
    fetch(`api/room_status.php?room_id=${roomId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.stats) {
                // Update student count
                const studentCountElements = document.querySelectorAll('.stat-value');
                if (studentCountElements[0]) {
                    studentCountElements[0].textContent = `${data.stats.students}/${data.stats.max_students}`;
                }
            }
        })
        .catch(error => {
            console.error('Error updating live stats:', error);
        });
}

// Initialize teacher live view
document.addEventListener('DOMContentLoaded', initTeacherLiveView);