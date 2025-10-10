"use client";

import { createContext, useContext, useState } from "react";

// Create the context
const EventMessageContext = createContext(null);

// Provider component
export function EventMessageProvider({ children }) {
	const [messageData, setMessageData] = useState(null);
	const [messageType, setMessageType] = useState("success");
	const [messageId, setMessageId] = useState(0); // Unique trigger

	// Enhanced function to show toast with unique ID
	const showToast = (message, type = "success") => {
		setMessageData(message);
		setMessageType(type);
		setMessageId((prev) => prev + 1); // Force re-trigger
	};

	return (
		<EventMessageContext.Provider
			value={{
				messageData,
				setMessageData,
				messageType,
				setMessageType,
				messageId,
				showToast,
			}}>
			{children}
		</EventMessageContext.Provider>
	);
}

// Custom hook to use the context
export function useEventMessage() {
	const context = useContext(EventMessageContext);
	if (!context) {
		throw new Error("useEventMessage must be used within an EventMessageProvider");
	}
	return context;
}
