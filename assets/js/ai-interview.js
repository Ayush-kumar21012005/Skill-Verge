// AI Interview JavaScript
let currentQuestion = 0
let totalQuestions = 10
let interviewData = {
  domain: "",
  difficulty: "",
  duration: 30,
  questions: [],
  responses: [],
  startTime: null,
}
let mediaRecorder = null
let audioChunks = []
let isRecording = false
let timer = null
let timeRemaining = 1800 // 30 minutes in seconds

// Initialize interview
document.addEventListener("DOMContentLoaded", () => {
  // Domain selection change handler
  document.getElementById("domain").addEventListener("change", function () {
    const selectedOption = this.options[this.selectedIndex]
    const description = selectedOption.getAttribute("data-description")
    document.getElementById("domain-description").textContent = description || ""
  })

  // Interview form submission
  document.getElementById("interview-form").addEventListener("submit", (e) => {
    e.preventDefault()
    startInterview()
  })
})

async function startInterview() {
  // Get form data
  interviewData.domain = document.getElementById("domain").value
  interviewData.difficulty = document.getElementById("difficulty").value
  interviewData.duration = Number.parseInt(document.getElementById("duration").value)

  if (!interviewData.domain || !interviewData.difficulty) {
    alert("Please select both domain and difficulty level")
    return
  }

  // Set timer based on duration
  timeRemaining = interviewData.duration * 60
  totalQuestions = Math.floor(interviewData.duration / 3) // Roughly 3 minutes per question

  try {
    // Request microphone permission
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })

    // Initialize MediaRecorder
    mediaRecorder = new MediaRecorder(stream)
    mediaRecorder.ondataavailable = (event) => {
      audioChunks.push(event.data)
    }

    // Generate questions
    await generateQuestions()

    // Hide setup and show interview interface
    document.getElementById("interview-setup").classList.add("d-none")
    document.getElementById("interview-interface").classList.remove("d-none")

    // Start the interview
    interviewData.startTime = new Date()
    displayCurrentQuestion()
    startTimer()
  } catch (error) {
    console.error("Error accessing microphone:", error)
    alert("Please allow microphone access to start the interview")
  }
}

async function generateQuestions() {
  // Simulate API call to generate questions based on domain and difficulty
  // In a real implementation, this would call your Python AI service

  const sampleQuestions = {
    "Software Development": [
      "What is object-oriented programming and what are its main principles?",
      "Explain the difference between SQL and NoSQL databases.",
      "How do you handle version control in your projects?",
      "What is the difference between synchronous and asynchronous programming?",
      "Describe the software development lifecycle.",
      "What are design patterns and can you give an example?",
      "How do you ensure code quality in your projects?",
      "Explain the concept of API and RESTful services.",
      "What is the difference between frontend and backend development?",
      "How do you approach debugging in your code?",
    ],
    "Data Science": [
      "What is the difference between supervised and unsupervised learning?",
      "How do you handle missing data in a dataset?",
      "Explain cross-validation and its importance.",
      "What is overfitting and how do you prevent it?",
      "Describe the data science workflow.",
      "What are the different types of data visualization?",
      "How do you evaluate the performance of a machine learning model?",
      "What is feature engineering and why is it important?",
      "Explain the bias-variance tradeoff.",
      "What are the ethical considerations in data science?",
    ],
  }

  const domainQuestions = sampleQuestions[interviewData.domain] || sampleQuestions["Software Development"]

  // Shuffle and select questions based on duration
  interviewData.questions = shuffleArray(domainQuestions).slice(0, totalQuestions)
}

function shuffleArray(array) {
  const shuffled = [...array]
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]]
  }
  return shuffled
}

function displayCurrentQuestion() {
  const question = interviewData.questions[currentQuestion]

  document.getElementById("interview-title").textContent = `${interviewData.domain} Interview`
  document.getElementById("interview-progress").textContent = `Question ${currentQuestion + 1} of ${totalQuestions}`
  document.getElementById("question-number").textContent = currentQuestion + 1
  document.getElementById("current-question").textContent = question

  // Update navigation buttons
  document.getElementById("prev-question").disabled = currentQuestion === 0

  if (currentQuestion === totalQuestions - 1) {
    document.getElementById("next-question").classList.add("d-none")
    document.getElementById("finish-interview").classList.remove("d-none")
  } else {
    document.getElementById("next-question").classList.remove("d-none")
    document.getElementById("finish-interview").classList.add("d-none")
  }
}

function startTimer() {
  timer = setInterval(() => {
    timeRemaining--

    const minutes = Math.floor(timeRemaining / 60)
    const seconds = timeRemaining % 60

    document.getElementById("timer").textContent =
      `${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`

    if (timeRemaining <= 0) {
      clearInterval(timer)
      finishInterview()
    }
  }, 1000)
}

function startRecording() {
  if (!mediaRecorder || isRecording) return

  audioChunks = []
  mediaRecorder.start()
  isRecording = true

  document.getElementById("start-recording").classList.add("d-none")
  document.getElementById("stop-recording").classList.remove("d-none")
  document.getElementById("recording-status").textContent = "Recording your answer..."
  document.getElementById("audio-visualizer").classList.remove("d-none")

  // Enable next button after recording starts
  document.getElementById("next-question").disabled = false
  document.getElementById("finish-interview").disabled = false
}

function stopRecording() {
  if (!mediaRecorder || !isRecording) return

  mediaRecorder.stop()
  isRecording = false

  document.getElementById("start-recording").classList.remove("d-none")
  document.getElementById("stop-recording").classList.add("d-none")
  document.getElementById("recording-status").textContent =
    "Recording saved. You can record again or move to the next question."
  document.getElementById("audio-visualizer").classList.add("d-none")

  // Save the response
  mediaRecorder.onstop = () => {
    const audioBlob = new Blob(audioChunks, { type: "audio/wav" })
    interviewData.responses[currentQuestion] = {
      question: interviewData.questions[currentQuestion],
      audioBlob: audioBlob,
      timestamp: new Date(),
    }
  }
}

function nextQuestion() {
  if (currentQuestion < totalQuestions - 1) {
    currentQuestion++
    displayCurrentQuestion()

    // Reset recording state
    document.getElementById("start-recording").classList.remove("d-none")
    document.getElementById("stop-recording").classList.add("d-none")
    document.getElementById("recording-status").textContent = "Click the microphone to start recording your answer"
    document.getElementById("next-question").disabled = true
    document.getElementById("finish-interview").disabled = true
  }
}

function previousQuestion() {
  if (currentQuestion > 0) {
    currentQuestion--
    displayCurrentQuestion()

    // Check if this question has a response
    if (interviewData.responses[currentQuestion]) {
      document.getElementById("next-question").disabled = false
      document.getElementById("finish-interview").disabled = false
      document.getElementById("recording-status").textContent = "You have already recorded an answer for this question."
    } else {
      document.getElementById("next-question").disabled = true
      document.getElementById("finish-interview").disabled = true
      document.getElementById("recording-status").textContent = "Click the microphone to start recording your answer"
    }
  }
}

async function finishInterview() {
  clearInterval(timer)

  // Show loading
  document.getElementById("interview-interface").innerHTML = `
        <div class="text-center py-5">
            <div class="spinner mb-3"></div>
            <h4>Analyzing your performance...</h4>
            <p class="text-muted">Our AI is evaluating your responses. This may take a few moments.</p>
        </div>
    `

  try {
    // Simulate AI analysis (in real implementation, send data to Python backend)
    await simulateAIAnalysis()

    // Show results
    document.getElementById("interview-interface").classList.add("d-none")
    document.getElementById("interview-results").classList.remove("d-none")

    // Save interview to database
    await saveInterviewResults()
  } catch (error) {
    console.error("Error analyzing interview:", error)
    alert("There was an error analyzing your interview. Please try again.")
  }
}

async function simulateAIAnalysis() {
  // Simulate API delay
  await new Promise((resolve) => setTimeout(resolve, 3000))

  // Generate mock scores (in real implementation, this comes from AI analysis)
  const technicalScore = Math.random() * 3 + 7 // 7-10 range
  const communicationScore = Math.random() * 3 + 7
  const confidenceScore = Math.random() * 3 + 7
  const overallScore = (technicalScore + communicationScore + confidenceScore) / 3

  // Update results display
  document.getElementById("overall-score").textContent = overallScore.toFixed(1)
  document.getElementById("technical-score").textContent = `${technicalScore.toFixed(1)}/10`
  document.getElementById("communication-score").textContent = `${communicationScore.toFixed(1)}/10`
  document.getElementById("confidence-score").textContent = `${confidenceScore.toFixed(1)}/10`

  document.getElementById("technical-progress").style.width = `${technicalScore * 10}%`
  document.getElementById("communication-progress").style.width = `${communicationScore * 10}%`
  document.getElementById("confidence-progress").style.width = `${confidenceScore * 10}%`

  // Store scores for saving
  interviewData.scores = {
    overall: overallScore,
    technical: technicalScore,
    communication: communicationScore,
    confidence: confidenceScore,
  }
}

async function saveInterviewResults() {
  try {
    const formData = new FormData()
    formData.append("domain", interviewData.domain)
    formData.append("difficulty", interviewData.difficulty)
    formData.append("duration", interviewData.duration)
    formData.append("questions", JSON.stringify(interviewData.questions))
    formData.append("scores", JSON.stringify(interviewData.scores))

    // Add audio files
    interviewData.responses.forEach((response, index) => {
      if (response && response.audioBlob) {
        formData.append(`audio_${index}`, response.audioBlob, `question_${index}.wav`)
      }
    })

    const response = await fetch("save-interview.php", {
      method: "POST",
      body: formData,
    })

    const result = await response.json()

    if (!result.success) {
      throw new Error(result.message)
    }
  } catch (error) {
    console.error("Error saving interview:", error)
    // Don't show error to user as the interview is complete
  }
}

function bookExpertSession() {
  window.location.href = "expert-interviews.php"
}

function startNewInterview() {
  // Reset all data
  currentQuestion = 0
  interviewData = {
    domain: "",
    difficulty: "",
    duration: 30,
    questions: [],
    responses: [],
    startTime: null,
  }

  // Show setup again
  document.getElementById("interview-results").classList.add("d-none")
  document.getElementById("interview-setup").classList.remove("d-none")

  // Reset form
  document.getElementById("interview-form").reset()
  document.getElementById("domain-description").textContent = ""
}
