import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    try {
      const response = await api.post('/login', {
        email,
        password,
      });

      login(response.data.token);

      // navega para a home protegida sem recarregar a página
      navigate('/');
    } catch {
      setError('Login inválido');
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-8">
        <h2 className="mb-6 text-xl font-bold text-slate-50">Login</h2>

        {error && <p className="mb-4 text-sm text-red-400">{error}</p>}

        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none"
          />

          <input
            type="password"
            placeholder="Senha"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none"
          />

          <button
            type="submit"
            className="mt-2 rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500"
          >
            Entrar
          </button>
        </form>

        <p className="mt-4 text-sm text-slate-400">
          <Link to="/esqueci-senha" className="text-indigo-400 hover:underline">
            Esqueci minha senha
          </Link>
        </p>

        <p className="mt-2 text-sm text-slate-400">
          Não tem conta?{' '}
          <Link to="/register" className="text-indigo-400 hover:underline">
            Criar conta
          </Link>
        </p>
      </div>
    </div>
  );
}

export default Login;
