import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../services/api';
import { useAuth } from '../context/AuthContext';

function Register() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }

    setSaving(true);

    try {
      const response = await api.post('/register', {
        name,
        email,
        password,
      });

      login(response.data.token);

      // navega para a home protegida sem recarregar a página
      navigate('/');
    } catch (err) {
      const validationErrors = err.response?.data?.errors;
      const firstError = validationErrors
        ? Object.values(validationErrors)[0]?.[0]
        : null;

      setError(firstError ?? 'Não foi possível criar a conta. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-8">
        <h2 className="mb-6 text-xl font-bold text-slate-50">Criar conta</h2>

        {error && <p className="mb-4 text-sm text-red-400">{error}</p>}

        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <input
            type="text"
            placeholder="Nome"
            value={name}
            onChange={e => setName(e.target.value)}
            disabled={saving}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={e => setEmail(e.target.value)}
            disabled={saving}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <input
            type="password"
            placeholder="Senha"
            value={password}
            onChange={e => setPassword(e.target.value)}
            disabled={saving}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <input
            type="password"
            placeholder="Confirmar senha"
            value={passwordConfirmation}
            onChange={e => setPasswordConfirmation(e.target.value)}
            disabled={saving}
            required
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <button
            type="submit"
            disabled={saving}
            className="mt-2 rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
          >
            {saving ? 'Criando conta...' : 'Criar conta'}
          </button>
        </form>

        <p className="mt-4 text-sm text-slate-400">
          Já tem conta?{' '}
          <Link to="/login" className="text-indigo-400 hover:underline">
            Entrar
          </Link>
        </p>
      </div>
    </div>
  );
}

export default Register;
