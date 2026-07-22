import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { resetPassword } from '../services/api';

function ResetPassword() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();

  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('As senhas não coincidem.');
      return;
    }

    setSaving(true);

    try {
      await resetPassword({ token, email, password });
      navigate('/login');
    } catch (err) {
      const validationErrors = err.response?.data?.errors;
      const firstError = validationErrors
        ? Object.values(validationErrors)[0]?.[0]
        : err.response?.data?.message;

      setError(firstError ?? 'Não foi possível redefinir a senha. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
        <div className="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-8">
          <h2 className="mb-4 text-xl font-bold text-slate-50">
            Link inválido
          </h2>
          <p className="text-sm text-slate-400">
            Este link de redefinição de senha está incompleto. Solicite um
            novo link.
          </p>
          <p className="mt-4 text-sm text-slate-400">
            <Link to="/esqueci-senha" className="text-indigo-400 hover:underline">
              Solicitar novo link
            </Link>
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-8">
        <h2 className="mb-6 text-xl font-bold text-slate-50">
          Redefinir senha
        </h2>

        {error && <p className="mb-4 text-sm text-red-400">{error}</p>}

        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <input
            type="password"
            placeholder="Nova senha"
            value={password}
            onChange={e => setPassword(e.target.value)}
            required
            disabled={saving}
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <input
            type="password"
            placeholder="Confirmar nova senha"
            value={passwordConfirmation}
            onChange={e => setPasswordConfirmation(e.target.value)}
            required
            disabled={saving}
            className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
          />

          <button
            type="submit"
            disabled={saving}
            className="mt-2 rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
          >
            {saving ? 'Salvando...' : 'Redefinir senha'}
          </button>
        </form>
      </div>
    </div>
  );
}

export default ResetPassword;
