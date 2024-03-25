Vue.component('poll-component', {
    template: `
    <div class="vue-poll">
        <h3 class="qst">{{ poll.title }}</h3>
        <div v-if="showResults" class="ans-cnt">
            <div v-for="(votes, answer) in poll.answers" :key="answer" class="answer-container">
                <div class="progress-bar-container">
                    <div :class="'progress-bar bar-' + answer" :style="{ width: calculatePercentage(votes) + '%' }"></div>
                    <span class="percentage">{{ Math.round(calculatePercentage(votes)) }}%</span> <!-- Round the percentage -->
                </div>
                <span class="answer-text">{{ answer }}</span> <!-- Show the answer text -->
                <span v-if="userSelectedAnswer === answer" class="checkmark">&#10004;</span> <!-- Checkmark if user selected this answer -->
            </div>
            <div class="total-votes">Total Votes: {{ totalVotes }}</div>
            <button @click="retakePoll" class="retake-btn">Retake Poll</button>
        </div>
        <div v-else>
            <div class="answer">
                <label v-for="(answer, index) in Object.keys(poll.answers)" :key="index" :class="{ 'selected': selectedAnswerIndex === index }" class="option-label">
                    <input type="radio" class="option-input" :value="index" v-model="selectedAnswerIndex">
                    <span>{{ answer }}</span>
                    <span v-if="selectedAnswerIndex === index" class="checkmark">&#10004;</span> <!-- Checkmark if this answer is selected -->
                </label>
            </div>
            <button v-if="selectedAnswerIndex !== null" @click="handleVote" class="submit-btn">Submit</button>
            <div v-if="showThankYou" class="thank-you-msg">Thank you for voting!</div>
        </div>
    </div>
    `,
     data() {
         return {
             poll: {},
             showResults: false,
             selectedAnswerIndex: null,
             totalVotes: 0,
             showThankYou: false, // New property to track if the vote has been submitted
             isLoading: true,
             userSelectedAnswer: null // Track the answer selected by the user
         }
     },
     props: ['pollId'], // Accept pollId as a prop
     mounted() {
         this.fetchPollData();
     },
     methods: {
        fetchPollData() {
            // Fetch the specific poll data based on the poll ID passed through the shortcode
            console.log('Fetching poll data for ID:', this.pollId); 
            fetch(`/wp-json/poll-plugin/v1/polls?id=${this.pollId}`)
            
                .then(response => response.json())
                .then(data => {
                    const poll = data.find(poll => poll.id === this.pollId); // Find the poll with the correct ID
                    if (poll) {
                        this.poll = poll;
                        this.calculateTotalVotes();
                    } else {
                        console.error(`Poll with ID ${this.pollId} not found.`);
                    }
                })
                .catch(error => {
                    console.error('Error fetching poll data:', error);
                })
                .finally(() => {
                    this.isLoading = false; // Set isLoading to false when data fetching is complete
                });
        
         },
         handleVote() {
            // Check if an answer is selected
            if (this.selectedAnswerIndex !== null) {
                // Send a POST request to update the poll data with the new vote
                fetch('/wp-json/poll-plugin/v1/vote', {
                    
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        pollId: this.poll.id,
                        answerIndex: this.selectedAnswerIndex
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to vote');
                    }
                    // Update the poll data and show results
                    this.showResults = true;
                    this.showThankYou = true; // Set to true to show the thank you message
                    this.userSelectedAnswer = Object.keys(this.poll.answers)[this.selectedAnswerIndex]; // Set the userSelectedAnswer
                    this.fetchPollData(); // Fetch updated poll data after voting
                })
                .catch(error => {
                    console.error('Error voting:', error);
                });
            } else {
                console.log('No answer selected'); // Debugging log
            }
        },        
         changeAnswer() {
             // Reset selected answer and show poll options again
             this.selectedAnswerIndex = null;
             this.showResults = false;
             this.showThankYou = false;
         },       
         calculateTotalVotes() {
            const votesArray = Object.values(this.poll.answers);
            this.totalVotes = votesArray.reduce((acc, cur) => acc + cur, 0);
        },
        calculatePercentage(votes) {
            return Math.round((votes / this.totalVotes) * 100); // Round the percentage
        },
        retakePoll() {
            // Reset the component state
            this.showResults = false;
            this.selectedAnswerIndex = null;
            this.showThankYou = false;
            this.userSelectedAnswer = null; // Reset userSelectedAnswer
            // Refetch poll data
            this.fetchPollData();
        }
        
     }
  });
  
// Create Vue app
const app = new Vue({
  el: '#app' // ID of the HTML element to mount the Vue app
});