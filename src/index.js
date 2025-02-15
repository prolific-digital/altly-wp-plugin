// src/index.js
import React from "react";
import { createRoot } from "react-dom/client";
const container = document.getElementById("altly-admin-app");
const root = createRoot(container);
import Shell from "./components/Shell";
import "./index.scss"; // Make sure this file exists and is processed

root.render(<Shell />);
