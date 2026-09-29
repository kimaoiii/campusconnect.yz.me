
// firebase-config.js
import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-auth.js";
import { getFirestore } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-firestore.js";

// Replace with your project's Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyBwj-9g1TS6ymhD8z3Luo0YN2fa05VTFfE",
    authDomain: "campusconnect-7c284.firebaseapp.com",
    projectId: "campusconnect-7c284",
    storageBucket: "campusconnect-7c284.firebasestorage.app",
    messagingSenderId: "191621639077",
    appId: "1:191621639077:web:b0a7e7092ddaba0e7a964c",
    measurementId: "G-PJHEZPHX6Y"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
export const db = getFirestore(app);