import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { LogIn, Send } from 'lucide-react';
import api, { resendVerification } from '../services/api';
import { useAuth } from '../context/AuthContext';
import AuthLayout from '../components/AuthLayout';
import { Input } from '../components/ui/input';
import { Button } from '../components/ui/button';
import { Alert, AlertDescription } from '../components/ui/alert';

function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [needsVerification, setNeedsVerification] = useState(false);
  const [resendState, setResendState] = useState('idle'); // idle | sending | sent
  const { login } = useAuth();
  const navigate = useNavigate();

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setNeedsVerification(false);
    setResendState('idle');

    try {
      const response = await api.post('/login', {
        email,
        password,
      });

      login(response.data.token);

      // navega para a home protegida sem recarregar a página
      navigate('/');
    } catch (err) {
      if (err.response?.status === 403) {
        setNeedsVerification(true);
        setError(err.response.data?.message ?? 'Confirme seu email antes de entrar.');
      } else {
        setError('Login inválido');
      }
    }
  }

  async function handleResend() {
    setResendState('sending');

    try {
      await resendVerification(email);
      setResendState('sent');
    } catch {
      setResendState('idle');
    }
  }

  return (
    <AuthLayout title="Login">
      {error && (
        <Alert variant="destructive" className="mb-4">
          <AlertDescription>
            {error}
            {needsVerification && (
              <div className="mt-2">
                {resendState === 'sent' ? (
                  <span className="text-foreground">
                    Se o email estiver cadastrado, reenviamos o link de confirmação.
                  </span>
                ) : (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleResend}
                    disabled={resendState === 'sending'}
                  >
                    <Send className="size-3.5" />
                    {resendState === 'sending' ? 'Enviando...' : 'Reenviar email de confirmação'}
                  </Button>
                )}
              </div>
            )}
          </AlertDescription>
        </Alert>
      )}

      <form onSubmit={handleSubmit} className="flex flex-col gap-3">
        <Input
          type="email"
          placeholder="Email"
          value={email}
          onChange={e => setEmail(e.target.value)}
          required
        />

        <Input
          type="password"
          placeholder="Senha"
          value={password}
          onChange={e => setPassword(e.target.value)}
          required
        />

        <Button type="submit" className="mt-2">
          <LogIn className="size-4" />
          Entrar
        </Button>
      </form>

      <p className="mt-4 text-sm text-muted-foreground">
        <Link to="/esqueci-senha" className="text-primary hover:underline">
          Esqueci minha senha
        </Link>
      </p>

      <p className="mt-2 text-sm text-muted-foreground">
        Não tem conta?{' '}
        <Link to="/register" className="text-primary hover:underline">
          Criar conta
        </Link>
      </p>
    </AuthLayout>
  );
}

export default Login;
