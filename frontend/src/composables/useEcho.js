import { ref } from "vue";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const echo = ref(null);
const isConnected = ref(false);

export function useEcho() {
  const connect = (options = {}) => {
    if (echo.value) {
      return echo.value;
    }

    const token = localStorage.getItem("token");

    const config = {
      broadcaster: "reverb",
      key: import.meta.env.VITE_REVERB_APP_KEY || "parayok-key",
      wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
      wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
      wssPort: import.meta.env.VITE_REVERB_PORT || 8080,
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME || "http") === "https",
      enabledTransports: ["ws", "wss"],
      disableStats: true, // Disable stats
      authEndpoint: `${import.meta.env.VITE_API_URL || "/api"}/broadcasting/auth`,
      auth: {
        headers: {
          Accept: "application/json",
          Authorization: token ? `Bearer ${token}` : undefined,
        },
      },
      ...options,
    };

    console.log("Echo Config:", config); // Debug connection

    echo.value = new Echo(config);

    echo.value.connector.pusher.connection.bind("state_change", (states) => {
      console.log("Echo State Change:", states);
    });

    // Debug all events
    echo.value.connector.pusher.bind_global((eventName, data) => {
      console.log("Global Event:", eventName, data);
    });

    echo.value.connector.pusher.connection.bind("connected", () => {
      isConnected.value = true;
    });

    echo.value.connector.pusher.connection.bind("disconnected", () => {
      isConnected.value = false;
    });

    return echo.value;
  };

  const disconnect = () => {
    if (echo.value) {
      echo.value.disconnect();
      echo.value = null;
      isConnected.value = false;
    }
  };

  const channel = (roomId) => {
    if (!echo.value) {
      throw new Error("Echo is not connected. Call connect() first.");
    }
    return echo.value.channel(`room.${roomId}`);
  };

  return {
    echo,
    isConnected,
    connect,
    disconnect,
    channel,
  };
}
