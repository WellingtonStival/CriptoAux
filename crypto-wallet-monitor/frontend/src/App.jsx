import { Routes, Route } from "react-router-dom";
import PrivateRoute from "./components/PrivateRoute";
import Dashboard from "./pages/Dashboard";
import News from "./pages/News";
import Wallets from "./pages/Wallets";
import WalletHistory from "./pages/WalletHistory";
import Login from "./pages/Login";
import Register from "./pages/Register";
import ForgotPassword from "./pages/ForgotPassword";
import ResetPassword from "./pages/ResetPassword";
import VerifyEmail from "./pages/VerifyEmail";

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route path="/esqueci-senha" element={<ForgotPassword />} />
      <Route path="/redefinir-senha" element={<ResetPassword />} />
      <Route path="/verificar-email" element={<VerifyEmail />} />

      <Route
        path="/"
        element={
          <PrivateRoute>
            <Dashboard />
          </PrivateRoute>
        }
      />

      <Route
        path="/wallets"
        element={
          <PrivateRoute>
            <Wallets />
          </PrivateRoute>
        }
      />

      <Route
        path="/noticias"
        element={
          <PrivateRoute>
            <News />
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