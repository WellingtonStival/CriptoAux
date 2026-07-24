import { useAuth } from "../context/AuthContext";
import Dashboard from "../pages/Dashboard";
import Landing from "../pages/Landing";

function HomeRoute() {
  const { token } = useAuth();

  return token ? <Dashboard /> : <Landing />;
}

export default HomeRoute;
