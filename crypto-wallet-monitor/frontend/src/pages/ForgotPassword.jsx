import { useState } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '../services/api';

function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSaving(true);

    try {
      await forgotPassword(email);
      setSent(true);
    } catch {
      setError('Não foi possível enviar o link. Tente novamente.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-900 px-4">
      <div className="w-full max-w-sm rounded-lg border border-slate-800 bg-slate-950 p-8">
        <h2 className="mb-6 text-xl font-bold text-slate-50">
          Esqueci minha senha
        </h2>

        {sent ? (
          <p className="text-sm text-slate-300">
            Se esse email estiver cadastrado, enviamos um link de
            redefinição de senha para ele. Confira sua caixa de entrada.
          </p>
        ) : (
          <>
            <p className="mb-4 text-sm text-slate-400">
              Informe o email da sua conta para receber um link de
              redefinição de senha.
            </p>

            {error && <p className="mb-4 text-sm text-red-400">{error}</p>}

            <form onSubmit={handleSubmit} className="flex flex-col gap-3">
              <input
                type="email"
                placeholder="Email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                required
                disabled={saving}
                className="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-slate-50 placeholder:text-slate-500 focus:border-slate-500 focus:outline-none disabled:opacity-60"
              />

              <button
                type="submit"
                disabled={saving}
                className="mt-2 rounded-md bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
              >
                {saving ? 'Enviando...' : 'Enviar link'}
              </button>
            </form>
          </>
        )}

        <p className="mt-4 text-sm text-slate-400">
          <Link to="/login" className="text-indigo-400 hover:underline">
            ← Voltar para o login
          </Link>
        </p>
      </div>
    </div>
  );
}

export default ForgotPassword;
