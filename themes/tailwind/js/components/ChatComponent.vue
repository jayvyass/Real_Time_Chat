<template>
    <div class="flex h-full">
      <!-- Sidebar with User List -->
      <div class="w-1/3 bg-gray-100 p-5 overflow-y-auto user-list-container">
        <div
          v-for="user in sortedUsers"
          :key="user.id"
          @click="selectUser(user)"
          @mouseenter="hoveredUser = user.id"
          @mouseleave="hoveredUser = null"
          :class="[
            'user-list-item flex items-center',
            {
              'bg-blue-500 text-black': selectedFriend && selectedFriend.id === user.id,
              'bg-gray-300': hoveredUser === user.id && (!selectedFriend || selectedFriend.id !== user.id)
            }
          ]"
        >
          <img v-if="user.photo" :src="profileImagePath(user.photo)" alt="Profile Picture" class="w-10 h-10 rounded-full mr-2">
          <div class="flex flex-col flex-grow">
            <div class="flex items-center justify-between">
              <div class="text-lg font-bold font-semibold">{{ user.name }}</div>
              <div v-if="user.notification_count > 0" class="h-4 w-4 bg-green-500 rounded-full flex items-center justify-center text-white text-xs">
                {{ user.notification_count }}
              </div>
            </div>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <div class="text-ml text-black-300">{{ truncatedLatestMessage(user.latest_message) }}</div>
              </div>
              <div class="text-xs text-black-200">{{ user.latest_message && user.latest_message.formatted_time ? user.latest_message.formatted_time : '' }}</div>
            </div>
          </div>
        </div>
      </div>
      <!-- Chat Container -->
      <div class="flex flex-col w-2/3 h-full relative">
        <!-- Header with Friend's Name and Three Dots -->
        <div class="flex items-center justify-between ml-2 mt-4 p-5 bg-blue-500 rounded-lg text-black relative">
          <div class="flex items-center">
            <img
              v-if="selectedFriend && selectedFriend.photo"
              :src="profileImagePath(selectedFriend.photo)"
              alt="Profile Picture"
              class="w-10 h-10 rounded-full"
            >
            <div class="text-lg p-1 font-semibold">{{ selectedFriend ? selectedFriend.name : 'Select a user to chat' }}</div>
          </div>
          <div class="relative">
            <div class="text-xl cursor-pointer" @click="toggleDropdown">⋮</div>
            <div v-if="dropdownOpen" class="absolute right-0 mt-2 w-48 bg-white border border-gray-300 rounded-lg shadow-lg z-10">
              <ul>
                <li class="px-4 py-2 hover:bg-blue-500 cursor-pointer" @click="confirmDeleteChat">Delete Chat</li>
              </ul>
            </div>
          </div>
        </div>
  
        <!-- Messages Container -->
        <div class="flex-1 p-4 overflow-y-auto messages-container" ref="messagesContainer">
          <div v-for="(message, index) in messages" :key="message.id" class="message-container">
            <div v-if="shouldDisplayDate(index)" class="text-center mb-2">
              <span class="bg-gray-200 text-sm px-2 py-1 rounded">{{ formatDate(message.created_at) }}</span>
            </div>
            <div v-if="message.sender_id === currentUser.id" class="flex flex-col items-end mb-1">
              <div v-if="shouldDisplayTime(index)" class="text-sm text-gray-400 mb-1">
                {{ formatTime(message.created_at) }}
              </div>
              <div class="p-2 text-white bg-blue-500 rounded-lg message whitespace-pre-wrap">
                {{ message.text }}
              </div>
              <small v-if="isLastMessageSentByCurrentUser(index)" class="text-xs text-gray-400">{{ message.mstatus }}</small>
            </div>
            <div v-else class="flex flex-col items-start mb-1">
              <div v-if="shouldDisplayTime(index)" class="text-sm text-gray-400 mb-1">
                {{ selectedFriend.name.split(' ')[0] }} at {{ formatTime(message.created_at) }}
              </div>
              <div class="p-2 bg-gray-200 rounded-lg message whitespace-pre-wrap">
                <span v-html="formatMessage(message.text)"></span>
              </div>
            </div>
          </div>
  
          <small v-if="isFriendTyping" class="p-2 text-gray-700 typing-indicator">
            {{ selectedFriend ? selectedFriend.name : 'Friend' }} is typing...
          </small>
        </div>
  
        <!-- Input and Send Button -->
        <div class="absolute bottom-1 left-0 right-0 flex items-center p-4 bg-white border-t input-container">
          <textarea
            type="text"
            v-model="newMessage"
            @keydown="sendTypingEvent"
            @keyup.enter="sendMessage"
            placeholder="Type a message..."
            class="flex-1 px-2 py-2 border rounded-lg"
          ></textarea>
          <button @click="sendMessage" class="px-4 py-2 ml-2 text-white bg-blue-500 rounded-lg">
            Send
          </button>
        </div>
      </div>
  
      <!-- Confirmation Modal -->
      <div v-if="showConfirmationModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96">
          <h2 class="text-lg font-semibold mb-4">Delete Chat</h2>
          <p>Are you sure you want to delete this chat?</p>
          <div class="flex justify-end mt-4">
            <button @click="showConfirmationModal = false" class="px-4 py-2 bg-gray-300 rounded-lg mr-2">Cancel</button>
            <button @click="deleteChat" class="px-4 py-2 bg-red-500 text-white rounded-lg">Delete</button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import axios from "axios";
  import { nextTick, onMounted, ref, watch, computed } from "vue";
  import { useRouter } from "vue-router";
  import dayjs from "dayjs";
  import relativeTime from "dayjs/plugin/relativeTime";
  
  dayjs.extend(relativeTime);
  
  const props = defineProps({
    currentUser: {
      type: Object,
      required: true,
    },
  });
  
  const users = ref([]);
  const messages = ref([]);
  const newMessage = ref("");
  const messagesContainer = ref(null);
  const isFriendTyping = ref(false);
  const isFriendTypingTimer = ref(null);
  const selectedFriend = ref(null);
  const dropdownOpen = ref(false);
  const showConfirmationModal = ref(false);
  const router = useRouter();
  const MESSAGE_LIMIT = 18; 
  const hoveredUser =ref(null);
  
  const profileImagePath = (photo) => {
    return `/storage/${photo}`;
  };
  
  onMounted(() => {
    loadUsers();
    Echo.private(`chat.${props.currentUser.id}`)
      .listen("MessageSent", (response) => {
        handleMessageSent(response.message);
        incrementNotificationCount(response.message.sender_id);
      })
      .listen("MessageSeen", (response) => {
        handleMessagesSeen(response.receiver_id);
      })
      .listenForWhisper("typing", (response) => {
        isFriendTyping.value = response.userID === selectedFriend.value?.id;
  
        if (isFriendTypingTimer.value) {
          clearTimeout(isFriendTypingTimer.value);
        }
  
        isFriendTypingTimer.value = setTimeout(() => {
          isFriendTyping.value = false;
        }, 1000);
      });
  });
  
  watch(
    () => selectedFriend.value,
    () => {
      if (selectedFriend.value) {
        loadMessages();
        resetNotificationCount(selectedFriend.value.id);
        axios.post(`/messages/${selectedFriend.value.id}/seen`);
      }
    }
  );
  
  watch(
    messages,
    () => {
      nextTick(() => {
        messagesContainer.value.scrollTo({
          top: messagesContainer.value.scrollHeight,
          behavior: "smooth",
        });
      });
    },
    { deep: true }
  );
  
  const loadUsers = () => {
    axios.get("/api/users").then((response) => {
      users.value = response.data;
    });
  };
  
  const selectUser = (user) => {
    selectedFriend.value = user;
    loadMessages();
  
    // Mark messages as seen
    axios.post(`/messages/${user.id}/seen`);
  };
  
  const formatMessage = (message) => {
    return message.replace(/\n/g, "<br>");
  };
  
  const loadMessages = () => {
    if (selectedFriend.value) {
      axios.get(`/messages/${selectedFriend.value.id}`).then((response) => {
        messages.value = response.data;
      });
    }
  };
  
  const sendMessage = () => {
    if (newMessage.value.trim() !== "" && selectedFriend.value) {
      axios
        .post(`/messages/${selectedFriend.value.id}`, {
          message: newMessage.value,
        })
        .then((response) => {
          handleMessageSent(response.data);
          newMessage.value = "";
        });
    }
  };
  
  const sendTypingEvent = () => {
    if (selectedFriend.value) {
      Echo.private(`chat.${selectedFriend.value.id}`).whisper("typing", {
        userID: props.currentUser.id,
      });
    }
  };
  
  const formatDate = (timestamp) => {
    const date = dayjs(timestamp);
    if (date.isSame(dayjs(), "day")) {
      return "Today";
    } else if (date.isSame(dayjs().subtract(1, "day"), "day")) {
      return "Yesterday";
    } else {
      return date.format("DD MMM, YY");
    }
  };
  
  const formatTime = (timestamp) => {
    return dayjs(timestamp).format("HH:mm");
  };
  
  const shouldDisplayDate = (index) => {
    if (index === 0) return true;
  
    const currentDate = dayjs(messages.value[index].created_at).format("YYYY-MM-DD");
    const previousDate = dayjs(messages.value[index - 1].created_at).format("YYYY-MM-DD");
  
    return currentDate !== previousDate;
  };
  
  const shouldDisplayTime = (index) => {
    if (index === 0) return true;
  
    const currentMessage = messages.value[index];
    const previousMessage = messages.value[index - 1];
  
    const currentTime = dayjs(currentMessage.created_at).format("HH:mm");
    const previousTime = dayjs(previousMessage.created_at).format("HH:mm");
  
    return currentMessage.sender_id !== previousMessage.sender_id || currentTime !== previousTime;
  };
  
  const handleMessageSent = (message) => {
    if (selectedFriend.value && (message.sender_id === selectedFriend.value.id || message.receiver_id === selectedFriend.value.id)) {
      messages.value.push(message);
    }
  
    // Update the latest message for the user in the user list
    const userIndex = users.value.findIndex(user => user.id === message.receiver_id || user.id === message.sender_id);
    if (userIndex !== -1) {
      users.value[userIndex].latest_message = message;
      // Update mstatus for the last message sent by the current user
      if (message.sender_id === props.currentUser.id) {
        users.value[userIndex].latest_message.mstatus = message.mstatus;
      }
    }
  };
  
  const handleMessagesSeen = (receiverId) => {
    if (selectedFriend.value && selectedFriend.value.id === receiverId) {
      messages.value.forEach(message => {
        if (message.sender_id === props.currentUser.id && message.mstatus === 'delivered') {
          message.mstatus = 'seen';
        }
      });
    }
  
    // Update the user list as well
    const userIndex = users.value.findIndex(user => user.id === receiverId);
    if (userIndex !== -1 && users.value[userIndex].latest_message.mstatus === 'delivered') {
      users.value[userIndex].latest_message.mstatus = 'seen';
    }
  };
  
  const truncatedLatestMessage = (message) => {
    if (message && message.status === 'away') {
      return "No messages yet";
    }
    if (message && message.text.length > MESSAGE_LIMIT) {
      return message.text.slice(0, MESSAGE_LIMIT) + "...";
    }
    return message ? message.text : "No messages yet";
  };
  
  const sortedUsers = computed(() => {
    return [...users.value].sort((a, b) => {
      if (a.latest_message && b.latest_message) {
        return dayjs(b.latest_message.created_at).unix() - dayjs(a.latest_message.created_at).unix();
      } else if (a.latest_message) {
        return -1;
      } else if (b.latest_message) {
        return 1;
      } else {
        return 0;
      }
    });
  });
  
  const isLastMessageSentByCurrentUser = (index) => {
    if (index === messages.value.length - 1) {
      // Check if the message at the current index is sent by the current user
      return messages.value[index].sender_id === props.currentUser.id;
    } else {
      // Ensure it doesn't show for previous messages sent by the current user
      return false;
    }
  };
  
  const toggleDropdown = () => {
    dropdownOpen.value = !dropdownOpen.value;
  };
  
  const confirmDeleteChat = () => {
    showConfirmationModal.value = true;
    dropdownOpen.value = false;
  };
  
  const deleteChat = () => {
    if (selectedFriend.value) {
      axios.delete(`/messages/${selectedFriend.value.id}`)
        .then(() => {
          // Set the status to "away" for the selected friend
          selectedFriend.value.status = "away";
          
          // Update the latest message for the user in the user list
          const userIndex = users.value.findIndex(user => user.id === selectedFriend.value.id);
          if (userIndex !== -1) {
            users.value[userIndex].latest_message = null; // Set the latest message to null or handle appropriately
          }
  
          // Clear the messages and reset the selected friend
          messages.value = [];
          selectedFriend.value = null;
          showConfirmationModal.value = false;
        })
        .catch(error => {
          console.error('Error deleting chat:', error);
          // Handle error appropriately
        });
    }
  };
  
  const resetNotificationCount = (userId) => {
    const user = users.value.find(user => user.id === userId);
    if (user) {
      user.notification_count = 0;
    }
  };
  
  const incrementNotificationCount = (senderId) => {
  const user = users.value.find(user => user.id === senderId);
  if (user && (!selectedFriend.value || user.id !== selectedFriend.value.id)) {
    user.notification_count += 1;
  }
};

  </script>
  
  <style scoped>
  .flex {
    display: flex;
  }
  .h-full {
    height: 100%;
  }
  .overflow-y-auto {
    overflow-y: auto;
  }
  .cursor-pointer {
    cursor: pointer;
  }
  .border {
    border: 1px solid #e5e7eb;
  }
  .border-t {
    border-top: 1px solid #e5e7eb;
  }
  .absolute {
    position: absolute;
    width: 100%;
  }
  .input-container {
    height: 4rem;
  }
  .typing-indicator {
    position: absolute;
    bottom: 4rem;
    left: 1rem;
    text-align: right;
  }
  .message {
    max-width: 75%;
    word-wrap: break-word;
    white-space: pre-wrap;
  }
  .message-container {
    margin-bottom: 0.5rem; /* Removed margin between messages */
  }
  .user-list-container {
    width: 30%; /* Adjust width as needed */
    padding: 1rem;
    border-right: 1px solid #e5e7eb;
    background-color: #f0f2f5;
  }
  .user-list-item {
    cursor: pointer;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem;
    transition: background-color 0.2s ease;
  }
  .user-list-item:hover {
    background-color: #3b82f6; /* bg-blue-500 */
  }
  .user-list-item .user-name {
    font-weight: bold;
  }
  .user-list-item .latest-message {
    font-size: 0.875rem;
    color: #6b7280;
  }
  .messages-container {
    margin-bottom: 4rem; /* Added margin between messages container and input/send button */
  }
  .user-list-container {
    width: 30%; /* Adjust width as needed */
    padding: 1rem;
    border-right: 1px solid #e5e7eb;
    background-color: #f0f2f5;
  }
  .user-list-item {
    cursor: pointer;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    border-radius: 0.5rem;
    transition: background-color 0.2s ease;
  }
  .user-list-item:hover {
    background-color: #dfe4ea;
  }
  .user-list-item .user-name {
    font-weight: bold;
  }
  .user-list-item .latest-message {
    font-size: 0.875rem;
    color: #6b7280;
  }
  .bg-blue-200 {
    background-color: #bfdbfe;
  }
  .bg-blue-500 {
    background-color: #3b82f6;
  }
  </style>
  