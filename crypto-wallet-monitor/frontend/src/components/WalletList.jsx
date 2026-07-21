import WalletItem from "./WalletItem";

function WalletList({ wallets, prices }) {
  return (
    <ul className="flex flex-col gap-3">
      {wallets.map(wallet => (
        <WalletItem key={wallet.id} wallet={wallet} prices={prices} />
      ))}
    </ul>
  );
}

export default WalletList;
