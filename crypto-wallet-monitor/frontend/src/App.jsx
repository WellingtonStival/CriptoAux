import { Routes, Route } from "react-router-dom";
import PrivateRoute from "./components/PrivateRoute";
import Wallets from "./pages/Wallets";
import WalletHistory from "./pages/WalletHistory";
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

      <Route
        path="/wallets/:id/history"
        element={
          <PrivateRoute>
            <WalletHistory />
          </PrivateRoute>
        }
      />
    </Routes>
  );
}

export default App;