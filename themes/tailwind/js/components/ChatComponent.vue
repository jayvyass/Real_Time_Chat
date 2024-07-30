<template>
  <div class="flex h-full">
   <!-- Sidebar with User List -->
   <div v-if="!isChatOpen" 
      :class="[
        'user-list-container bg-gray-100 p-5 overflow-y-auto',
        isMobileView && !isChatOpen ? 'w-full' : (isEmployee ? 'w-1/3' : 'w-1/3'),
      ]"
>
      <input type="text" v-model="searchQuery" @input="filterUsers" placeholder="Search users" class="w-full px-3 py-2 border rounded-lg mt-1 mb-4">
      <div
        v-for="user in filteredUsers"
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
    <div
      :class="[
      'chat-container flex flex-col h-full relative',
      isEmployee ? (isMobileView && isChatOpen ? 'w-full' : 'w-2/3') : 'w-2/3',
      isMobileView && !isChatOpen ? 'hidden' : ''
    ]"
    >
      <!-- Header with Friend's Name and Three Dots -->
      <div class="flex items-center justify-between ml-2 mt-4 p-5 bg-blue-500 rounded-lg text-black relative">
        <div class="flex items-center">
          <!-- Back Button for Mobile View -->
          <div v-if="isMobileView" @click="closeChat" class="text-xl cursor-pointer mr-2">⬅️</div>
          <img
            v-if="selectedFriend && selectedFriend.photo"
            :src="profileImagePath(selectedFriend.photo)"
            alt="Profile Picture"
            class="w-10 h-10 rounded-full"
          >
          <div class="text-lg p-1 font-semibold">
            {{ selectedFriend ? selectedFriend.name : 'Select a user to chat' }}
          </div>
        </div>
        <div class="relative">
          <div class="text-xl cursor-pointer" @click="toggleDropdown">⋮</div>
          <div
            v-if="dropdownOpen"
            class="absolute right-0 mt-2 w-40 bg-white border border-gray-300 rounded-lg shadow-lg z-10"
          >
            <ul>
              <li class="px-4 py-2 hover:bg-white-500 cursor-pointer" @click="confirmDeleteChat">Delete Chat</li>
            </ul>
          </div>
        </div>
      </div>


      <!-- Messages Container -->
      <div class="flex-1 p-4 overflow-y-auto messages-container" ref="messagesContainer">
        <div v-for="(message, index) in messages" :key="message.id" class="message-container relative">
          <div v-if="shouldDisplayDate(index)" class="text-center mb-2 mt-4">
            <span class="bg-gray-200 text-sm px-2 py-1 rounded">{{ formatDate(message.created_at) }}</span>
          </div>
          <div v-if="message.sender_id === currentUser.id" class="flex flex-col items-end mb-1">
            <div v-if="shouldDisplayTime(index)" class="text-sm text-gray-400 mb-1">
              {{ formatTime(message.created_at) }}
            </div>
            <div v-if="message.image" class="p-2">
              <img :src="profileImagePath(message.image)" alt="Image" class="rounded-lg max-w-xs">
            </div>
            <div 
              v-if="!message.image && message.text" 
              class="p-2 text-white bg-blue-500 rounded-lg message whitespace-pre-wrap relative"
              @click="toggleMessageDropdown(index)"
            >
              {{ message.text }}
              <div v-if="messageDropdownOpen === index" class="absolute right-0 mt-2 w-40 bg-white border border-gray-300 rounded-lg shadow-lg z-10">
                <ul>
                  <li class="px-4 text-black py-2 hover:bg-white-500 cursor-pointer" @click="editMessage(message, index)">Edit Message</li>
                  <li class="px-4 text-black py-2 hover:bg-white-500 cursor-pointer" @click="copyMessage(message.text)">Copy Message</li>
                </ul>
              </div>
            </div>
            <small v-if="isLastMessageSentByCurrentUser(index)" class="text-xs text-gray-400">{{ message.mstatus }}</small>
          </div>
          <div v-else class="flex flex-col items-start mb-1">
            <div v-if="shouldDisplayTime(index)" class="text-sm text-gray-400 mb-1">
              {{ selectedFriend.name.split(' ')[0] }} at {{ formatTime(message.created_at) }}
            </div>
            <div v-if="message.image" class="p-2">
              <img :src="profileImagePath(message.image)" alt="Image" class="rounded-lg max-w-xs">
            </div>
            <div v-if="!message.image && message.text" class="p-2 bg-gray-200 rounded-lg message whitespace-pre-wrap">
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
          v-model="newMessage"
          @keydown.enter="handleKeyDown"
          @keyup.enter="sendMessage"
          @keydown="sendTypingEvent"
          placeholder="Type a message..."
          class="flex-1 px-1 py-1 border rounded-lg"
          style="resize: none;"
        ></textarea>
        <input type="file" ref="imageInput" @change="uploadImage" class="hidden">
        <button @click="triggerImageUpload" class="px-4 py-2 ml-2 text-white bg-blue-500 rounded-lg">
          📷
        </button> 
        <button @click="sendMessage" class="px-4 py-2 ml-2 text-white bg-blue-500 rounded-lg">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a.75.75 0 01-.75-.75V4.537l-5.22 5.221a.75.75 0 01-1.06-1.06l6.5-6.5a.75.75 0 011.06 0l6.5 6.5a.75.75 0 11-1.06 1.06L10.75 4.537V17.25A.75.75 0 0110 18z" clip-rule="evenodd" />
          </svg>
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

    <!-- Toast Container -->
    <div class="toast-container" v-if="toastMessage">{{ toastMessage }}</div>
  </div>
</template>


<script setup>
import axios from "axios";
import { nextTick, onMounted, ref, watch , computed } from "vue";
import { useRouter } from "vue-router";
import dayjs from 'dayjs';
import isToday from 'dayjs/plugin/isToday';
import isYesterday from 'dayjs/plugin/isYesterday';
dayjs.extend(isToday);
dayjs.extend(isYesterday);

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
const MESSAGE_LIMIT = 24; 
const hoveredUser = ref(null);
const searchQuery = ref('');
const filteredUsers = ref([]);
const imageInput = ref(null); 
const messageDropdownOpen = ref(null); 
const editMessageIndex = ref(null); 
const editMessageId = ref(null); 
const toastMessage = ref(null);
const profileImagePath = (photo) => `/storage/${photo}`;
const isMobileView = ref(window.innerWidth <= 768);
const isChatOpen = ref(false);

window.addEventListener("resize", () => {
  isMobileView.value = window.innerWidth <= 768;
});

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
    if (isMobileView.value) {
      isChatOpen.value = true;
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

const isEmployee = computed(() => 
  props.currentUser.role === 'employee' || props.currentUser.role === 'admin'
);

const loadUsers = () => {
  axios.get("/api/users").then((response) => {
    users.value = response.data;
    filterUsers();
  });
};

const selectUser = (user) => {
  selectedFriend.value = user;
  loadMessages();
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

const toggleMessageDropdown = (index) => {
  messageDropdownOpen.value = messageDropdownOpen.value === index ? null : index;
};

const sendMessage = () => {
  if (newMessage.value.trim() !== "" && selectedFriend.value) {
    if (editMessageId.value !== null) {
      axios.put(`/messages/${selectedFriend.value.id}/${editMessageId.value}`, {
        message: newMessage.value,
      }).then((response) => {
        messages.value[editMessageIndex.value].text = newMessage.value;
        updateLatestMessageInUserList(newMessage.value);
        clearEditState();
      });
    } else {
      axios.post(`/messages/${selectedFriend.value.id}`, {
        message: newMessage.value,
      }).then((response) => {
        handleMessageSent(response.data);
        newMessage.value = "";
        searchQuery.value = "";
        filterUsers();
      });
    }
  }
};

const clearEditState = () => {
  newMessage.value = "";
  editMessageId.value = null;
  editMessageIndex.value = null;
  messageDropdownOpen.value = null;
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
  } else if (date.isYesterday()) {
    return "Yesterday";
  } else if (date.isAfter(dayjs().subtract(1, 'week'))) {
    return date.format("ddd");
  } else {
    return date.format("DD/MM/YY");
  }
};

const formatTime = (timestamp) => dayjs(timestamp).format("HH:mm");

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
  const userIndex = users.value.findIndex(user => user.id === message.receiver_id || message.sender_id === user.id);
  if (userIndex !== -1) {
    users.value[userIndex].latest_message = message;
    if (message.sender_id === props.currentUser.id) {
      users.value[userIndex].latest_message.mstatus = message.mstatus;
    }
  }
  filterUsers();
};

const handleMessagesSeen = (receiverId) => {
  if (selectedFriend.value && selectedFriend.value.id === receiverId) {
    messages.value.forEach(message => {
      if (message.sender_id === props.currentUser.id && message.mstatus === 'delivered') {
        message.mstatus = 'seen';
      }
    });
  }
  const userIndex = users.value.findIndex(user => user.id === receiverId);
  if (userIndex !== -1 && users.value[userIndex].latest_message.mstatus === 'delivered') {
    users.value[userIndex].latest_message.mstatus = 'seen';
  }
};

const truncatedLatestMessage = (message, role) => {
  if (message && message.status === 'away') {
    // Check role to display appropriate message
    return role !== 'employee' ? "Send message to admin" : "No messages yet";
  }
  if (message && message.image) {
    return "Photo 📷";
  }
  if (message && message.text.length > MESSAGE_LIMIT) {
    return message.text.slice(0, MESSAGE_LIMIT) + "...";
  }
  return message ? message.text : (role !== 'employee' ? "Send message to admin " : "No messages yet");
};


const isLastMessageSentByCurrentUser = (index) => {
  if (index === messages.value.length - 1) {
    return messages.value[index].sender_id === props.currentUser.id;
  } else {
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
        selectedFriend.value.status = "away";
        const userIndex = users.value.findIndex(user => user.id === selectedFriend.value.id);
        if (userIndex !== -1) {
          users.value[userIndex].latest_message = null;
        }
        messages.value = [];
        selectedFriend.value = null;
        showConfirmationModal.value = false;
        filterUsers();
      })
      .catch(error => {
        console.error('Error deleting chat:', error);
      });
  }
};

const editMessage = (message, index) => {
  newMessage.value = message.text;
  editMessageId.value = message.id;
  editMessageIndex.value = index;
  messageDropdownOpen.value = null;
};

const copyMessage = (messageText) => {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(messageText)
      .then(() => {
        showToast('Message copied to clipboard');
      })
      .catch(error => {
        console.error('Failed to copy text: ', error);
      });
  } else {
    const textarea = document.createElement('textarea');
    textarea.value = messageText;
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showToast('Message copied to clipboard');
    } catch (error) {
      console.error('Failed to copy text: ', error);
    }
    document.body.removeChild(textarea);
  }
};

const showToast = (message) => {
  toastMessage.value = message;
  setTimeout(() => {
    toastMessage.value = null;
  }, 2000);
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
  filterUsers();
};

const filterUsers = () => {
  const query = searchQuery.value.toLowerCase();
  const isGuest = props.currentUser.role === 'guest';
  const isEmployee = props.currentUser.role === 'employee';

let filtered = users.value.filter(user => {
  if (isGuest) {
    // For guests, only include users with the role 'admin'
    if (user.role === 'admin') {
      return true;
    }
  } else {
    // For employees and admins
    if (query.length >= 3) {
      // Exclude users with role 'guest' if current user is an employee
      return (user.role !== 'guest' || !isEmployee) && user.name.toLowerCase().includes(query);
    } else {
      // Exclude users with role 'guest' if current user is an employee
      return (user.role !== 'guest' || !isEmployee) && (user.latest_message && user.latest_message.content !== "No Messages Yet");
    }
  }
  // If not filtered by the above conditions, return false by default
  return false;
});

  // Format message timestamps
  filtered.forEach(user => {
    if (user.latest_message) {
      const createdAt = dayjs(user.latest_message.created_at);
      const oneWeekAgo = dayjs().subtract(1, 'week');

      if (createdAt.isToday()) {
        user.latest_message.formatted_time = createdAt.format('HH:mm');
      } else if (createdAt.isYesterday()) {
        user.latest_message.formatted_time = createdAt.format('ddd');
      } else if (createdAt.isAfter(oneWeekAgo)) {
        user.latest_message.formatted_time = createdAt.format('ddd');
      } else {
        user.latest_message.formatted_time = createdAt.format('DD/MM/YY');
      }
    }
  });

  // Sort users by the latest message timestamp
  filtered.sort((a, b) => {
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

  filteredUsers.value = filtered;
};



const triggerImageUpload = () => {
  if (imageInput.value) {
    imageInput.value.click();
  }
  filterUsers();
};

const uploadImage = (event) => {
  const file = event.target.files[0];
  if (file && selectedFriend.value) {
    const formData = new FormData();
    formData.append("image", file);
    axios.post(`/messages/${selectedFriend.value.id}/upload`, formData, {
      headers: {
        "Content-Type": "multipart/form-data"
      }
    }).then((response) => {
      handleMessageSent(response.data);
      filterUsers();
            
    }).catch(error => {
      console.error('Error uploading image:', error);
    });
  }
};

const updateLatestMessageInUserList = (newText) => {
  if (selectedFriend.value) {
    const userIndex = users.value.findIndex(user => user.id === selectedFriend.value.id);
    if (userIndex !== -1) {
      users.value[userIndex].latest_message.text = newText;
      filterUsers();
    }
  }
};

const closeChat = () => {
  isChatOpen.value = false;
  filterUsers();
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
padding: 10px;
border-bottom: 1px solid #ddd;
transition: background-color 0.3s ease;
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
.relative {
position: relative;
}
.absolute {
  position: absolute;
}
.mt-2 {
  margin-top: 0.5rem;
}
.w-40 {
  width: 10rem;
}
.bg-white {
  background-color: #fff;
}
.border {
  border: 1px solid;
}
.border-gray-300 {
  border-color: #d1d5db;
}
.rounded-lg {
  border-radius: 0.5rem;
}
.shadow-lg {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.z-10 {
  z-index: 10;
}
.px-4 {
  padding-left: 1rem;
  padding-right: 1rem;
}
.py-2 {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}
.hover\:bg-blue-500:hover {
  background-color: #3b82f6;
}
.cursor-pointer {
  cursor: pointer;
}
.toast-container {
  position: fixed;
  top: 3rem;
  left: 50%;
  transform: translateX(-50%);
  background-color: #09e677;
  color: rgb(68, 64, 64);
  padding: 1rem;
  border-radius: 0.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: center;
  align-items: center;
}

@media (max-width: 768px) {
  .user-list-container {
    width: 100%;
    display: block;
  }

  .chat-container {
    width: 100%;
  }

  .chat-container.hidden {
    display: none;
  }
}
</style>
