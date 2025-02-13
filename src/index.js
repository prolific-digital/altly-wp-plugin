// src/index.js
import React from "react";
import { render } from "react-dom";
import Shell from "./components/Shell";
import "./index.scss"; // Make sure this file exists and is processed

render(<Shell />, document.getElementById("altly-admin-app"));
