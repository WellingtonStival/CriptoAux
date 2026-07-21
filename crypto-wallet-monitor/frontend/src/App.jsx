import { Routes, Route } from "react-router-dom";
import PrivateRoute from "./components/PrivateRoute";
import Wallets from "./pages/Wallets";
import Login from "./pages/Login";
import Register from "./pages/Register";

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />

      <Route
        path="/"
        element={
          <PrivateRoute>
            <Wallets />
          </PrivateRoute>
        }
      />
    </Routes>
  );
}

export default App;