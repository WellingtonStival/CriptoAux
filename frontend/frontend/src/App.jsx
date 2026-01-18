import { Routes, Route } from "react-router-dom";
import PrivateRoute from "./components/PrivateRoute";
import Wallets from "./pages/Wallets";
import Login from "./pages/Login";

function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />

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